<?php

namespace App\Services;

use App\Models\User;

/**
 * The single definition of "which leads may this user see" and "what do the
 * lead filters mean".
 *
 * The leads page, the list view and the export each used to carry their own
 * copy of these rules, and the copies had drifted: the export ignored six of
 * the filters the page offers and applied a different visibility rule, so the
 * downloaded file did not match what was on screen. Everything now calls in
 * here instead.
 */
class LeadScope
{
    /** User types that see every lead of their company. */
    const PRIVILEGED_TYPES = ['company', 'CSO', 'Alaa,Saba,aber', 'fadi'];

    /**
     * The countries offered when creating or editing a lead, and therefore the
     * only ones worth offering in the filter. The filter used to be built from
     * the distinct values actually stored in leads.country, which is how phone
     * numbers written into that column ended up listed as countries.
     *
     * Kept identical to the list in resources/views/leads/create.blade.php and
     * edit.blade.php - those two still carry their own copy.
     */
    const COUNTRIES = [
        'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria',
        'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia',
        'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cambodia',
        'Cameroon', 'Canada', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica',
        'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt',
        'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon',
        'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti',
        'Honduras', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Jamaica', 'Japan',
        'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia',
        'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta',
        'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro',
        'Morocco', 'Mozambique', 'Myanmar', 'Namibia', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger',
        'Nigeria', 'North Korea', 'North Macedonia', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine', 'Panama', 'Papua New Guinea',
        'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saint Kitts and Nevis',
        'Saint Lucia', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia',
        'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia',
        'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria',
        'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia',
        'Turkey', 'Turkmenistan', 'Tuvalu', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States',
        'Uruguay', 'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe', 'Other',
    ];

    /**
     * Restrict $query to the leads $usr is allowed to see. Always scopes to the
     * user's own company first.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public static function applyVisibility($query, $usr)
    {
        $query->where('leads.created_by', '=', $usr->creatorId());

        // Sales Manager sees only leads assigned to someone in their sales group.
        if ($usr->hasRole('Sales Manager') || $usr->hasRole('sales manager')) {
            if (!$usr->sales_group) {
                return $query->whereRaw('1 = 0');
            }

            $groupUserIds = User::where('created_by', $usr->creatorId())
                ->where('sales_group', $usr->sales_group)
                ->pluck('id')->toArray();

            return self::whereAssignedToAny($query, $groupUserIds);
        }

        if (in_array($usr->type, self::PRIVILEGED_TYPES) || $usr->can('view all leads')) {
            return $query;
        }

        // Everyone else sees only the leads assigned to them.
        return self::whereAssignedToAny($query, [$usr->id]);
    }

    /**
     * Apply every filter the leads page offers. Unset filters are ignored, so
     * this is safe to call with a bare request.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Http\Request $request
     */
    /** The deal-status filter value for leads without any deal. */
    const NO_DEAL = 'none';

    /** The deal statuses the company's deals use, most used first, for the filter. */
    public static function dealStatuses(int $companyId): array
    {
        return \DB::table('deals')->where('created_by', $companyId)->whereNotNull('status')->where('status', '<>', '')
            ->groupBy('status')->orderByRaw('COUNT(*) DESC')->pluck('status')->all();
    }

    public static function applyFilters($query, $request, $usr = null)
    {
        // The quick search box posts the same term as both name and phone.
        if ($request->name && $request->phone && $request->name === $request->phone) {
            $searchTerm = $request->name;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('leads.name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('leads.phone', 'like', '%' . $searchTerm . '%');
            });
        } else {
            if ($request->name) {
                $query->where('leads.name', 'like', '%' . $request->name . '%');
            }
            if ($request->phone) {
                $query->where('leads.phone', 'like', '%' . $request->phone . '%');
            }
        }

        if ($request->subject) {
            $query->where('leads.subject', 'like', '%' . $request->subject . '%');
        }

        if ($request->users) {
            $user_ids = explode(',', $request->users);
            $query->where(function ($q) use ($user_ids) {
                $q->whereIn('leads.user_id', $user_ids)
                    ->orWhereExists(function ($subQ) use ($user_ids) {
                        $subQ->select(\DB::raw(1))
                            ->from('user_leads')
                            ->whereColumn('user_leads.lead_id', 'leads.id')
                            ->whereIn('user_leads.user_id', $user_ids);
                    });
            });
        }

        foreach (['products' => 'products', 'sources' => 'sources', 'labels' => 'labels', 'interests' => 'interests'] as $param => $column) {
            if ($request->$param) {
                $ids = explode(',', $request->$param);
                $query->where(function ($q) use ($ids, $column) {
                    foreach ($ids as $id) {
                        $q->orWhereRaw("FIND_IN_SET(?, leads.$column)", [$id]);
                    }
                });
            }
        }

        // By the status of the lead's deals: any deal with that status, or no deal at all.
        if ($request->deal_status === self::NO_DEAL) {
            $query->whereNotExists(function ($s) {
                $s->select(\DB::raw(1))->from('deals as dsf')->whereColumn('dsf.lead_id', 'leads.id');
            });
        } elseif ($request->deal_status) {
            $query->whereExists(function ($s) use ($request) {
                $s->select(\DB::raw(1))->from('deals as dsf')->whereColumn('dsf.lead_id', 'leads.id')
                    ->where('dsf.status', $request->deal_status);
            });
        }
        if ($request->country) {
            $query->where('leads.country', 'like', '%' . $request->country . '%');
        }
        if ($request->city) {
            $query->where('leads.city', 'like', '%' . $request->city . '%');
        }
        if ($request->campaign_name) {
            $query->where('leads.campaign_name', $request->campaign_name);
        }
        if ($request->costing_type) {
            $query->where('leads.costing_type', 'like', '%' . $request->costing_type . '%');
        }
        if ($request->gender) {
            $query->where('leads.gender', $request->gender);
        }

        if ($request->min_cost && $request->max_cost) {
            $query->whereBetween('leads.cost', [$request->min_cost, $request->max_cost]);
        } elseif ($request->min_cost) {
            $query->where('leads.cost', '>=', $request->min_cost);
        } elseif ($request->max_cost) {
            $query->where('leads.cost', '<=', $request->max_cost);
        }

        if ($request->date) {
            $date = explode(' to ', $request->date);
            if (count($date) == 2) {
                $query->whereBetween('leads.date', [$date[0], $date[1]]);
            } else {
                $query->where('leads.date', $request->date);
            }
        }

        if ($request->updated_date) {
            $updated_date = explode(' to ', $request->updated_date);
            if (count($updated_date) == 2) {
                $query->whereBetween('leads.updated_at', [$updated_date[0] . ' 00:00:00', $updated_date[1] . ' 23:59:59']);
            } else {
                $query->whereDate('leads.updated_at', $request->updated_date);
            }
        }

        if ($request->sales_group) {
            $usr = $usr ?: \Auth::user();
            $groupUserIds = User::where('created_by', $usr->creatorId())
                ->where('sales_group', $request->sales_group)
                ->pluck('id')->toArray();

            if (empty($groupUserIds)) {
                $query->whereRaw('1 = 0');
            } else {
                self::whereAssignedToAny($query, $groupUserIds);
            }
        }

        if ($request->unassigned) {
            $query->whereDoesntHave('users');
        }

        if ($request->no_activity) {
            $query->whereDoesntHave('activities')
                ->whereDoesntHave('discussions')
                ->whereDoesntHave('calls')
                ->whereDoesntHave('deals')
                ->whereDoesntHave('salesMeetings');
        }

        return $query;
    }

    /**
     * The people a lead can belong to: anyone in the company who sits in a Sales
     * department, has a Sales user type, or holds a Sales role. This is the same
     * definition the "Assign user" dialog uses, so the Users filter lists exactly
     * the people who can own a lead instead of every employee in the company.
     *
     * employees is LEFT joined on purpose: a user with a Sales role but no
     * employee record used to be dropped from the filter entirely.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function salesUsersQuery($usr)
    {
        return User::select('users.name', 'users.id')
            ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
            ->where('users.created_by', '=', $usr->creatorId())
            ->where('users.type', '!=', 'client')
            ->where('users.type', '!=', 'company')
            ->where(function ($q) {
                $q->where('departments.name', 'LIKE', '%Sales%')
                    ->orWhere('users.type', 'LIKE', '%Sales%')
                    ->orWhereExists(function ($sub) {
                        $sub->select(\DB::raw(1))
                            ->from('model_has_roles')
                            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                            ->whereColumn('model_has_roles.model_id', 'users.id')
                            ->where('roles.name', 'LIKE', '%Sales%');
                    });
            })
            ->distinct();
    }

    /**
     * whereExists against user_leads rather than a join, so a lead assigned to
     * two people in the group is still returned once.
     */
    private static function whereAssignedToAny($query, array $userIds)
    {
        return $query->whereExists(function ($subQ) use ($userIds) {
            $subQ->select(\DB::raw(1))
                ->from('user_leads')
                ->whereColumn('user_leads.lead_id', 'leads.id')
                ->whereIn('user_leads.user_id', $userIds);
        });
    }
}
