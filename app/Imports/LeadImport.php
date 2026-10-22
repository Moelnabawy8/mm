<?php

namespace App\Imports;

use App\Models\Label;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\ProductService;
use App\Models\Source;
use App\Models\User;
use App\Models\UserLead;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class LeadImport implements ToCollection, WithHeadingRow
{
    private $dryRun = false;
    private $errors = [];
    private $rowsProcessed = 0;
    private $rowsImported = 0;

    public function __construct($dryRun = false)
    {
        $this->dryRun = $dryRun;
        // Keep default behavior for heading names (slugged) or change if needed.
        // Assuming default is fine as original code used $row['name'] etc keys which implies slugged headers.
    }

    public function collection(Collection $rows)
    {
        $creatorId = \Auth::user()->creatorId();
        $user = \Auth::user();

        // If no rows, nothing to do
        if ($rows->isEmpty()) {
            return;
        }

        // Get valid Sales department users for validation (once for performance)
        $salesUsers = User::select('users.name', 'users.id')
            ->join('employees', 'users.id', '=', 'employees.user_id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('departments.name', 'LIKE', '%Sales%')
            ->where('users.created_by', '=', $creatorId)
            ->where('users.type', '!=', 'client')
            ->where('users.type', '!=', 'company')
            ->distinct()
            ->get();
        $salesUserNames = $salesUsers->pluck('name')->map(fn($n) => strtolower(trim($n)))->toArray();
        $salesUsersByName = $salesUsers->keyBy(fn($u) => strtolower(trim($u->name)));

        // Get valid Sources for validation from the database
        $validSources = Source::where('created_by', $creatorId)->pluck('name')->map(fn($s) => strtolower(trim($s)))->toArray();
        $validSourcesOriginal = Source::where('created_by', $creatorId)->pluck('name')->toArray();

        // Get valid Sources for validation from the database (Dynamic as requested)
        $validSources = Source::where('created_by', $creatorId)->pluck('name')->map(fn($s) => strtolower(trim($s)))->toArray();
        $validSourcesOriginal = Source::where('created_by', $creatorId)->pluck('name')->toArray();

        // Predefined list of allowed Countries (Robust list including user examples)
        // You can add more countries here as needed.
        $allowedCountries = [
            'egypt', 'ksa', 'uae', 'saudi arabia', 'united arab emirates', 'qatar', 'kuwait', 'bahrain', 'oman', 
            'jordan', 'lebanon', 'palestine', 'syria', 'iraq', 'libya', 'tunisia', 'algeria', 'morocco', 'sudan', 
            'yemen', 'turkey', 'usa', 'uk', 'france', 'germany', 'italy', 'spain', 'canada', 'australia', 'russia', 
            'china', 'india', 'japan', 'brazil', 'mexico', 'south africa', 'other'
        ];
        $allowedCountriesDisplay = [
            'Egypt', 'KSA', 'UAE', 'Saudi Arabia', 'United Arab Emirates', 'Qatar', 'Kuwait', 'Bahrain', 'Oman', 
            'Jordan', 'Lebanon', 'Palestine', 'Syria', 'Iraq', 'Libya', 'Tunisia', 'Algeria', 'Morocco', 'Sudan', 
            'Yemen', 'Turkey', 'USA', 'UK', 'France', 'Germany', 'Italy', 'Spain', 'Canada', 'Australia', 'Russia', 
            'China', 'India', 'Japan', 'Brazil', 'Mexico', 'South Africa', 'Other'
        ];

        $processedEmails = [];
        $processedPhones = [];

        // ── Pre-scan: collect all individual phones/emails from the file and check DB duplicates ──
        $allFilePhonesCleaned = [];
        $allFileEmails = [];
        foreach ($rows as $index => $row) {
            $rawPhone = $row['phone'] ?? $row['phone_number'] ?? $row['mobile'] ?? $row['contact_phone'] ?? '';
            $cleanedPhone = $this->cleanPhoneNumbers($rawPhone);
            if (!empty($cleanedPhone)) {
                // Split multi-numbers to check each individually
                foreach (explode(',', $cleanedPhone) as $p) {
                    $allFilePhonesCleaned[] = $p;
                }
            }
            
            $email = trim($row['email'] ?? '');
            if (!empty($email)) $allFileEmails[$index] = $email;
        }
        $allFilePhonesCleaned = array_unique($allFilePhonesCleaned);

        // Check DB for duplicate phones
        $duplicatePhones = [];
        if (!empty($allFilePhonesCleaned)) {
            // We search for any lead that has any of these numbers in their phone field (stored as CSV)
            $existingLeads = Lead::where('created_by', $creatorId)
                ->where(function ($query) use ($allFilePhonesCleaned) {
                    foreach ($allFilePhonesCleaned as $phone) {
                        $query->orWhere('phone', 'LIKE', '%' . $phone . '%');
                    }
                })
                ->pluck('phone')
                ->toArray();
            
            // Refine matching: ensure it's not a partial match (e.g., 123 matching 123456)
            foreach ($existingLeads as $dbPhone) {
                $dbNumbers = explode(',', $dbPhone);
                foreach ($dbNumbers as $dbN) {
                    // Normalize DB number for comparison
                    $cleanDbN = preg_replace('/[^0-9]/', '', $dbN);
                    if (in_array($cleanDbN, $allFilePhonesCleaned)) {
                        $duplicatePhones[] = $dbN;
                        break;
                    }
                }
            }
            $duplicatePhones = array_unique($duplicatePhones);
        }

        // Check DB for duplicate emails
        $duplicateEmails = [];
        if (!empty($allFileEmails)) {
            $existingEmails = Lead::where('created_by', $creatorId)
                ->whereIn('email', array_values($allFileEmails))
                ->pluck('email')
                ->toArray();
            $duplicateEmails = array_unique($existingEmails);
        }

        // If ANY DB duplicates found, reject the entire file
        if (!empty($duplicatePhones) || !empty($duplicateEmails)) {
            $dupMessages = [];
            if (!empty($duplicatePhones)) {
                $dupMessages[] = __('Duplicate phone numbers already in system') . ': ' . implode(', ', $duplicatePhones);
            }
            if (!empty($duplicateEmails)) {
                $dupMessages[] = __('Duplicate emails already in system') . ': ' . implode(', ', $duplicateEmails);
            }

            // Add one error entry listing all duplicates so the whole file is rejected
            $this->errors[] = [
                'row' => 0,
                'data' => [],
                'error' => implode(' | ', $dupMessages),
            ];
            return; // Stop processing – entire file rejected
        }
        // ── End pre-scan ──

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 for 0-index, +1 for header row
            $this->rowsProcessed++;
            $rowErrors = [];

            // 1. Validate Mandatory Fields
            $name = $row['name'] ?? $row['lead_name'] ?? $row['client_name'] ?? $row['customer_name'] ?? null;
            if (empty(trim($name ?? ''))) {
                $rowErrors[] = __('Name is required');
            }

            // 2. Validate Phone is required
            $phone = $row['phone'] ?? $row['phone_number'] ?? $row['mobile'] ?? $row['contact_phone'] ?? null;
            $phone = $this->cleanPhoneNumbers($phone ?? '');
            if (empty($phone)) {
                $rowErrors[] = __('Phone is required');
            }

            // 3. Validate Lead Sources is required and valid
            $sourceNames = $row['sources'] ?? $row['lead_sources'] ?? $row['source'] ?? null;
            if (empty(trim($sourceNames ?? ''))) {
                $rowErrors[] = __('Source is required');
            } else {
                $inputSources = explode(',', $sourceNames);
                foreach ($inputSources as $inputSource) {
                    $trimmedSource = strtolower(trim($inputSource));
                    if (!in_array($trimmedSource, $validSources)) {
                        $rowErrors[] = __('Invalid Source') . ': "' . trim($inputSource) . '". ' . __('Allowed sources are') . ': ' . implode(', ', $validSourcesOriginal);
                        break;
                    }
                }
            }

            // 4. Validate Country is required and valid
            $country = trim($row['country'] ?? '');
            if (empty($country)) {
                $rowErrors[] = __('Country is required');
            } else {
                if (!in_array(strtolower($country), $allowedCountries)) {
                    $rowErrors[] = __('Invalid Country') . ': "' . $country . '". ' . __('Allowed countries are') . ': ' . implode(', ', $allowedCountriesDisplay);
                }
            }

            // 5. Notes is optional - no validation needed

            // 6. Validate Users (optional)

            // 7. Validate Duplicates (Email/Phone) within file only
            $email = $row['email'] ?? null;
            $email = trim($email ?? '');

            if (!empty($email) || !empty($phone)) {
                // Check internal duplicates (within this file only)
                if (
                    (!empty($email) && in_array($email, $processedEmails)) ||
                    (!empty($phone) && in_array($phone, $processedPhones))
                ) {
                    $rowErrors[] = __('Duplicate Email or Phone (in file)');
                }

                // Track processed emails/phones to prevent duplicates within the same file
                if (!empty($email))
                    $processedEmails[] = $email;
                if (!empty($phone))
                    $processedPhones[] = $phone;
            }

            if (!empty($rowErrors)) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'data' => $row->toArray(),
                    'error' => implode(', ', $rowErrors),
                ];
                continue; // Skip this row
            }


            if ($this->dryRun) {
                continue;
            }

            // If NOT dry run, and no error, we proceed to save.
            $this->importRow($row, $creatorId, $user);
            $this->rowsImported++;
        }
    }

    private function importRow($row, $creatorId, $currentUser)
    {
        // Resolve Created By (if present)
        $createdByName = $row['created_by'] ?? null;
        if (!empty($createdByName)) {
            $creator = User::where('name', $createdByName)->first();
            if ($creator) {
                $creatorId = $creator->id;
            }
        }

        // 1. Resolve User (Case-insensitive) - More flexible, search all users
        // Don't default to current user - let it be null if not specified
        $userId = null;
        $userName = $row['user'] ?? $row['assigned_to'] ?? $row['employee'] ?? null;

        if (!empty(trim($userName ?? ''))) {
            $user = User::where('created_by', $creatorId)
                ->where(function ($q) use ($userName) {
                    $q->where('name', $userName)
                        ->orWhere('name', 'LIKE', $userName);
                })
                ->first();

            if ($user) {
                $userId = $user->id;
            } else {
                // If user not found with creatorId, try without it
                $user = User::where(function ($q) use ($userName) {
                    $q->where('name', $userName)
                        ->orWhere('name', 'LIKE', $userName);
                })->first();

                if ($user) {
                    $userId = $user->id;
                }
            }
        }

        // If still no user found, use current user as fallback
        if (!$userId) {
            $userId = $currentUser->id;
        }

        // 2. Resolve Pipeline
        $pipelineName = $row['pipeline'] ?? null;
        $pipelineId = null;

        if (!empty($pipelineName)) {
            $pipeline = Pipeline::where('created_by', $creatorId)
                ->where(function ($query) use ($pipelineName) {
                    $query->where('name', $pipelineName)
                        ->orWhere('name', 'LIKE', trim($pipelineName));
                })
                ->first();

            if ($pipeline) {
                $pipelineId = $pipeline->id;
            }
        }

        if (!$pipelineId) {
            $pipeline = Pipeline::where('created_by', $creatorId)->first();
            if ($pipeline) {
                $pipelineId = $pipeline->id;
            }
        }


        // 3. Resolve Stage
        $stageName = $row['lead_stage'] ?? null;
        $stageId = null;

        if ($pipelineId) {
            if (!empty($stageName)) {
                $stage = LeadStage::where('pipeline_id', $pipelineId)
                    ->where(function ($query) use ($stageName) {
                        $query->where('name', $stageName)
                            ->orWhere('name', 'LIKE', trim($stageName));
                    })
                    ->first();
                if ($stage) {
                    $stageId = $stage->id;
                }
            }

            // Fallback to first stage of pipeline if not found
            if (!$stageId) {
                $stage = LeadStage::where('pipeline_id', $pipelineId)->first();
                if ($stage) {
                    $stageId = $stage->id;
                }
            }
        }

        // 4. Resolve Sources
        $sourceNames = $row['sources'] ?? $row['lead_sources'] ?? null;
        $sourceIds = $this->mapNamesToIds(Source::class, $sourceNames, $creatorId);

        // 5. Resolve Products with optional category and type filtering
        $productNames = $row['products'] ?? null;
        $productCategory = $row['product_category'] ?? $row['category'] ?? null;
        $productType = $row['product_type'] ?? $row['type'] ?? null;
        $productIds = $this->mapNamesToIds(ProductService::class, $productNames, $creatorId, $productCategory, $productType);

        // 6. Resolve Labels
        $labelNames = $row['labels'] ?? null;
        $labelIds = $this->mapNamesToIds(Label::class, $labelNames, $creatorId);

        // Map phone again just to be sure and clean it
        $phone = $row['phone'] ?? $row['phone_number'] ?? $row['mobile'] ?? $row['contact_phone'] ?? null;
        $phone = $this->cleanPhoneNumbers($phone ?? '');
        $email = $row['email'] ?? null;

        // Get name from various possible column names
        $name = $row['name'] ?? $row['lead_name'] ?? $row['client_name'] ?? $row['customer_name'] ?? null;

        $leadData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'user_id' => $userId,
            'pipeline_id' => $pipelineId,
            'stage_id' => $stageId,
            'sources' => $sourceIds,
            'products' => $productIds,
            'labels' => $labelIds,
            'notes' => $row['notes'] ?? null,
            'gender' => $row['gender'] ?? $row['sex'] ?? null,
            'country' => !empty($row['country']) ? trim($row['country']) : null,
            'city' => $row['city'] ?? null,
            'costing_type' => !empty($row['costing_type'] ?? $row['coasting_type'] ?? null) ? strtolower(trim($row['costing_type'] ?? $row['coasting_type'])) : null,
            'campaign_name' => $row['campaign_name'] ?? null,
            'cost' => $row['cost'] ?? null,
            'telegram' => $row['telegram'] ?? null,
            'interests' => $row['interests'] ?? null,
            'is_active' => 1,
            'date' => date('Y-m-d'),
            'created_by' => $creatorId,
        ];

        // Always create new lead (duplicates are rejected in the pre-scan phase)
        $lead = Lead::create($leadData);

        if ($lead) {
            // Collect all user IDs to assign - start with primary user only if specified
            $usersToAssign = [];
            if ($userId) {
                $usersToAssign[] = $userId;
            }

            // Process Assigned Users column if present (supports both 'users' and 'assigned_users' column names)
            $assignedUsersNames = $row['users'] ?? $row['assigned_users'] ?? null;
            if (!empty($assignedUsersNames)) {
                $names = explode(',', $assignedUsersNames);
                foreach ($names as $name) {
                    $name = trim($name);
                    if (empty($name))
                        continue;

                    // Resolve User (Case-insensitive) - More flexible search
                    $u = User::where('created_by', $creatorId)
                        ->where(function ($q) use ($name) {
                            $q->where('name', $name)->orWhere('name', 'LIKE', $name);
                        })
                        ->first();

                    // If not found, try without created_by restriction
                    if (!$u) {
                        $u = User::where(function ($q) use ($name) {
                            $q->where('name', $name)->orWhere('name', 'LIKE', $name);
                        })->first();
                    }

                    if ($u) {
                        $usersToAssign[] = $u->id;
                    }
                }
            }

            // Deduplicate
            $usersToAssign = array_unique($usersToAssign);



            // Create new user assignments only if we have users to assign
            if (!empty($usersToAssign)) {
                foreach ($usersToAssign as $uid) {
                    UserLead::create([
                        'user_id' => $uid,
                        'lead_id' => $lead->id,
                    ]);
                }
            } else {
                // If no users specified, assign to current user
                UserLead::create([
                    'user_id' => $currentUser->id,
                    'lead_id' => $lead->id,
                ]);
            }
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getImportedCount()
    {
        return $this->rowsImported;
    }

    /**
     * Clean phone numbers by removing spaces and formatting multiple numbers with comma separator
     */
    private function cleanPhoneNumbers($phone)
    {
        if (empty($phone)) {
            return '';
        }

        // Split by comma to handle multiple numbers as in Create/Edit
        $numbers = explode(',', $phone);
        $cleanedNumbers = [];

        foreach ($numbers as $number) {
            // Remove all spaces, +, and non-digit characters to treat "+963 998 37" same as "96399837"
            $cleaned = preg_replace('/[^0-9]/', '', $number);
            if (!empty($cleaned)) {
                $cleanedNumbers[] = $cleaned;
            }
        }

        return implode(',', $cleanedNumbers);
    }

    private function mapNamesToIds($modelClass, $nameString, $creatorId, $category = null, $type = null)
    {
        if (empty($nameString)) {
            return '';
        }

        $names = explode(',', $nameString);
        $ids = [];

        foreach ($names as $name) {
            $name = trim($name);
            if (empty($name))
                continue;

            $query = $modelClass::where('created_by', $creatorId)
                ->where('name', 'LIKE', $name);

            // Apply category filter for ProductService
            if ($modelClass === ProductService::class && !empty($category)) {
                $categoryRecord = \App\Models\ProductServiceCategory::where('created_by', $creatorId)
                    ->where('name', 'LIKE', trim($category))
                    ->first();

                if ($categoryRecord) {
                    $query->where('category_id', $categoryRecord->id);
                }
            }

            // Apply type filter for ProductService (product vs service)
            if ($modelClass === ProductService::class && !empty($type)) {
                $type = strtolower(trim($type));
                if (in_array($type, ['product', 'service'])) {
                    $query->where('type', $type);
                }
            }

            $record = $query->first();

            if ($record) {
                $ids[] = $record->id;
            }
        }

        return implode(',', $ids);
    }
}
