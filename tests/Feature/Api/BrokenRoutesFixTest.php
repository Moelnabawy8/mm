<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceEmployee;
use App\Models\Document;
use App\Models\DucumentUpload;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Notification;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SupportRequest;
use App\Models\TimeTracker;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 20 /api/v1 routes returned 500 (13 pointed at controller methods that did not
 * exist, 7 legacy routes called methods that need an {id} the URL did not have),
 * and 4 more were unreachable because a /{id} route registered first caught them.
 */
class BrokenRoutesFixTest extends TestCase
{
    use DatabaseTransactions;

    protected $company;
    protected $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();
        app()['cache']->forget('spatie.permission.cache');

        $this->company = $this->makeUser('company', 1, [
            'manage support request', 'create support request', 'edit support request',
            'create document type', 'delete document type',
            'manage document', 'create document', 'delete document',
            'manage attendance', 'create attendance',
        ]);
        $this->otherCompany = $this->makeUser('company', 1);
    }

    protected function makeUser($type, $createdBy, array $perms = [])
    {
        static $n = 0;
        $n++;
        $u = new User();
        $u->name = 'brf' . $n;
        $u->email = 'brf' . $n . '_' . uniqid() . '@test.local';
        $u->password = bcrypt('secret');
        $u->type = $type;
        $u->created_by = $createdBy;
        $u->lang = 'en';
        $u->email_verified_at = now();
        $u->save();

        if ($perms) {
            $role = Role::firstOrCreate(['name' => 'brfRole' . $n . uniqid(), 'guard_name' => 'web'], ['created_by' => $createdBy]);
            foreach ($perms as $p) {
                if ($perm = Permission::where('name', $p)->first()) {
                    $role->givePermissionTo($perm);
                }
            }
            $u->assignRole($role);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }

        return $u->fresh();
    }

    protected function makeProject($owner)
    {
        $p = new Project();
        $p->project_name = 'BRF ' . uniqid();
        $p->client_id = 0;
        $p->status = 'in_progress';
        $p->created_by = $owner->id;
        $p->save();

        $t = new ProjectTask();
        $t->name = 'BRF task';
        $t->project_id = $p->id;
        $t->stage_id = 0;
        $t->created_by = $owner->id;
        $t->save();

        return [$p, $t];
    }

    protected function makeLead($owner)
    {
        $pipeline = Pipeline::create(['name' => 'Main', 'created_by' => $owner->id]);
        $stage = LeadStage::create(['name' => 'New', 'pipeline_id' => $pipeline->id, 'created_by' => $owner->id, 'order' => 0]);
        $lead = new Lead();
        $lead->name = 'BRF lead';
        $lead->email = 'l' . uniqid() . '@test.local';
        $lead->subject = 'test';
        $lead->user_id = $owner->id;
        $lead->pipeline_id = $pipeline->id;
        $lead->stage_id = $stage->id;
        $lead->created_by = $owner->id;
        $lead->date = date('Y-m-d');
        $lead->save();

        return $lead;
    }

    // ---------------------------------------------------------------- support requests

    public function test_support_request_lifecycle()
    {
        $supporter = $this->makeUser('employee', $this->company->id, ['manage support request', 'edit support request']);
        $lead = $this->makeLead($this->company);
        Sanctum::actingAs($this->company);

        $res = $this->postJson('/api/v1/support-requests', [
            'lead_id' => $lead->id, 'supporter_id' => $supporter->id,
            'notes' => 'Please call this client today', 'priority' => 'high',
        ])->assertStatus(201)->assertJsonPath('data.support_request.status', 'pending');
        $id = $res->json('data.support_request.id');
        $this->assertTrue(Notification::where('user_id', $supporter->id)->where('type', 'support_request_created')->exists());

        $this->getJson('/api/v1/support-requests')->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->getJson("/api/v1/support-requests/$id")->assertOk()->assertJsonPath('data.support_request.id', $id);

        $this->putJson("/api/v1/support-requests/$id", [
            'lead_id' => $lead->id, 'supporter_id' => $supporter->id,
            'notes' => 'Updated notes, still urgent', 'priority' => 'urgent',
        ])->assertOk()->assertJsonPath('data.support_request.priority', 'urgent');

        // only the supporter may change status
        $this->postJson("/api/v1/support-requests/$id/update-status", ['status' => 'completed'])->assertStatus(403);

        Sanctum::actingAs($supporter);
        $this->postJson("/api/v1/support-requests/$id/update-status", ['status' => 'in_progress'])->assertOk();
        $this->putJson("/api/v1/support-requests/$id", ['status' => 'completed', 'response_notes' => 'done', 'client_subscribed' => true, 'subscription_amount' => 250])
            ->assertOk()->assertJsonPath('data.support_request.status', 'completed');

        $sr = SupportRequest::find($id);
        $this->assertNotNull($sr->responded_at);
        $this->assertNotNull($sr->completed_at);
        $this->assertTrue(Notification::where('user_id', $this->company->id)->where('type', 'support_request_completed')->exists());
    }

    public function test_support_request_rejects_other_company_lead_and_supporter()
    {
        $foreignLead = $this->makeLead($this->otherCompany);
        $foreignUser = $this->makeUser('employee', $this->otherCompany->id);
        $mySupporter = $this->makeUser('employee', $this->company->id);
        $myLead = $this->makeLead($this->company);
        Sanctum::actingAs($this->company);

        $this->postJson('/api/v1/support-requests', ['lead_id' => $foreignLead->id, 'supporter_id' => $mySupporter->id, 'notes' => 'cross tenant lead', 'priority' => 'low'])
            ->assertStatus(422);
        $this->postJson('/api/v1/support-requests', ['lead_id' => $myLead->id, 'supporter_id' => $foreignUser->id, 'notes' => 'cross tenant user', 'priority' => 'low'])
            ->assertStatus(422);
    }

    public function test_support_request_hidden_from_non_participants()
    {
        $supporter = $this->makeUser('employee', $this->company->id);
        $outsider = $this->makeUser('employee', $this->company->id, ['manage support request', 'edit support request']);
        $sr = SupportRequest::create(['lead_id' => $this->makeLead($this->company)->id, 'requester_id' => $this->company->id, 'supporter_id' => $supporter->id, 'status' => 'pending', 'notes' => 'x', 'priority' => 'low']);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/support-requests/{$sr->id}")->assertStatus(404);
        $this->postJson("/api/v1/support-requests/{$sr->id}/update-status", ['status' => 'cancelled'])->assertStatus(404);
    }

    // ---------------------------------------------------------------- documents

    public function test_document_types_create_and_delete()
    {
        Sanctum::actingAs($this->company);
        $id = $this->postJson('/api/v1/documents', ['name' => 'Passport', 'is_required' => true])
            ->assertStatus(201)->json('data.document_type.id');
        $this->getJson("/api/v1/documents/$id")->assertOk();
        $this->deleteJson("/api/v1/documents/$id")->assertOk();
        $this->assertNull(Document::find($id));

        $foreign = Document::create(['name' => 'X', 'is_required' => 0, 'created_by' => $this->otherCompany->id]);
        $this->deleteJson("/api/v1/documents/{$foreign->id}")->assertStatus(404);
    }

    public function test_document_uploads_crud_and_download()
    {
        Sanctum::actingAs($this->company);
        $res = $this->post('/api/v1/document-uploads', [
            'name' => 'Handbook', 'description' => 'HR handbook',
            'document' => UploadedFile::fake()->create('handbook.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $res->assertStatus(201);
        $id = $res->json('data.document.id');
        $this->assertStringEndsWith('_handbook.pdf', $res->json('data.document.file_name'));

        $this->getJson('/api/v1/document-uploads')->assertOk()->assertJsonPath('data.documents.0.id', $id);
        $this->getJson("/api/v1/document-uploads/$id")->assertOk();
        $this->assertStringContainsString('uploads/documentUpload/', $this->getJson("/api/v1/document-uploads/$id/download")->assertOk()->json('data.download_url'));
        $this->deleteJson("/api/v1/document-uploads/$id")->assertOk();
        $this->assertNull(DucumentUpload::find($id));

        @unlink(storage_path('uploads/documentUpload/' . $res->json('data.document.file_name')));
    }

    // ---------------------------------------------------------------- attendance

    public function test_attendance_import_and_export()
    {
        $emp = Employee::create(['name' => 'Imp', 'email' => 'imp' . uniqid() . '@test.local', 'created_by' => $this->company->id]);
        $foreign = Employee::create(['name' => 'For', 'email' => 'for' . uniqid() . '@test.local', 'created_by' => $this->otherCompany->id]);
        Sanctum::actingAs($this->company);

        $csv = "Email,Date,Clock In,Clock Out\n{$emp->email},2026-10-01,09:30,19:00\n{$foreign->email},2026-10-01,09:00,18:00\n{$emp->email},not-a-date,09:00,18:00\n";
        $res = $this->post('/api/v1/attendance/import', ['file' => UploadedFile::fake()->createWithContent('a.csv', $csv)], ['Accept' => 'application/json']);
        $res->assertOk()->assertJsonPath('data.imported', 1);
        $this->assertCount(2, $res->json('data.failed'));

        $row = AttendanceEmployee::where('employee_id', $emp->id)->where('date', '2026-10-01')->first();
        $this->assertNotNull($row);
        $this->assertSame('09:30:00', $row->clock_in);
        $this->assertSame('19:00:00', $row->clock_out);
        $this->assertFalse(AttendanceEmployee::where('employee_id', $foreign->id)->exists());

        $this->get('/api/v1/attendance/export')->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_bulk_attendance_keeps_clock_out()
    {
        $emp = Employee::create(['name' => 'Bulk', 'email' => 'b' . uniqid() . '@test.local', 'created_by' => $this->company->id]);
        Sanctum::actingAs($this->company);

        $this->postJson('/api/v1/attendance/bulk', ['date' => '2026-10-02', 'employees' => [['employee_id' => $emp->id, 'clock_in' => '09:00', 'clock_out' => '17:00']]])->assertOk();
        $this->assertSame('17:00:00', AttendanceEmployee::where('employee_id', $emp->id)->first()->clock_out);
    }

    public function test_mark_attendance_no_longer_crashes()
    {
        $user = $this->makeUser('employee', $this->company->id);
        Employee::create(['name' => 'Mark', 'user_id' => $user->id, 'email' => $user->email, 'created_by' => $this->company->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/attendance', ['date' => '2026-10-03', 'clock_in' => '09:00'])->assertOk();
    }

    // ---------------------------------------------------------------- projects / tasks

    public function test_project_file_upload_goes_to_a_task_of_that_project()
    {
        [$project, $task] = $this->makeProject($this->company);
        [, $foreignTask] = $this->makeProject($this->otherCompany);
        Sanctum::actingAs($this->company);

        $file = UploadedFile::fake()->create('spec.txt', 1, 'text/plain');
        $res = $this->post("/api/v1/projects/{$project->id}/files", ['task_id' => $task->id, 'file' => $file], ['Accept' => 'application/json']);
        $res->assertOk()->assertJsonPath('data.file.task_id', $task->id);
        $this->getJson("/api/v1/projects/{$project->id}/files")->assertOk()->assertJsonCount(1, 'data.files');

        $this->post("/api/v1/projects/{$project->id}/files", ['task_id' => $foreignTask->id, 'file' => UploadedFile::fake()->create('x.txt', 1)], ['Accept' => 'application/json'])
            ->assertStatus(422);
        @unlink(storage_path('app/uploads/tasks/' . $res->json('data.file.file')));
    }

    public function test_task_check_in_and_out()
    {
        [$project, $task] = $this->makeProject($this->company);
        $task2 = ProjectTask::create(['name' => 'BRF task 2', 'project_id' => $project->id, 'stage_id' => 0, 'created_by' => $this->company->id]);
        [, $foreignTask] = $this->makeProject($this->otherCompany);
        Sanctum::actingAs($this->company);

        $this->postJson("/api/v1/tasks/{$task->id}/check-in")->assertOk();
        $this->postJson("/api/v1/tasks/{$task->id}/check-in")->assertStatus(409);
        // one running timer per user, as in /time-tracker/start and the web tracker
        $this->postJson("/api/v1/tasks/{$task2->id}/check-in")->assertStatus(409)->assertJsonPath('data.active_tracker.task_id', $task->id);
        $this->getJson('/api/v1/time-tracker/current')->assertOk()->assertJsonPath('data.tracker.task_id', $task->id);

        $this->postJson("/api/v1/tasks/{$task->id}/check-out")->assertOk();
        $tracker = TimeTracker::where('task_id', $task->id)->first();
        $this->assertNotNull($tracker->end_time);
        $this->assertEquals(0, $tracker->is_active);
        $this->postJson("/api/v1/tasks/{$task->id}/check-out")->assertStatus(404);
        $this->postJson("/api/v1/tasks/{$task2->id}/check-in")->assertOk();

        $this->postJson("/api/v1/tasks/{$foreignTask->id}/check-in")->assertStatus(404);
        $this->postJson("/api/v1/tasks/{$foreignTask->id}/check-out")->assertStatus(404);
    }

    public function test_time_tracker_stop_clears_is_active()
    {
        Sanctum::actingAs($this->company);
        $id = $this->postJson('/api/v1/time-tracker/start', ['name' => 'focus'])->assertOk()->json('data.tracker.id');
        $this->postJson('/api/v1/time-tracker/stop', ['tracker_id' => $id])->assertOk();
        $this->assertEquals(0, TimeTracker::find($id)->is_active);

        // both eager-load project/task(/user), relations the model did not have
        $this->getJson('/api/v1/time-tracker/current')->assertOk();
        $this->getJson('/api/v1/time-tracker/history')->assertOk();
    }

    public function test_legacy_project_routes_work_with_body_ids()
    {
        [$project, $task] = $this->makeProject($this->company);
        Sanctum::actingAs($this->company);

        $this->getJson("/api/v1/get-project-tasks?project_id={$project->id}")->assertOk()->assertJsonCount(1, 'data.tasks');
        $this->getJson("/api/v1/get-project-task?task_id={$task->id}")->assertOk()->assertJsonPath('data.task.id', $task->id);
        $this->postJson('/api/v1/update-project', ['project_id' => $project->id, 'project_name' => 'Renamed'])->assertOk();
        $this->assertSame('Renamed', $project->fresh()->project_name);
        $this->postJson('/api/v1/update-project-task', ['task_id' => $task->id, 'name' => 'T2', 'estimated_hrs' => 3, 'priority' => 'low', 'stage_id' => 0, 'start_date' => '2026-10-01'])->assertOk();
        $this->assertSame('T2', $task->fresh()->name);
        $this->postJson('/api/v1/duplicate-project', ['project_id' => $project->id])->assertOk();
        $this->postJson('/api/v1/delete-project-task', ['task_id' => $task->id])->assertOk();
        $this->postJson('/api/v1/delete-project', ['project_id' => $project->id])->assertOk();
        $this->assertNull(Project::find($project->id));
    }

    public function test_legacy_project_routes_refuse_other_companies_rows()
    {
        [$project, $task] = $this->makeProject($this->otherCompany);
        Sanctum::actingAs($this->company);

        $this->getJson("/api/v1/get-project-tasks?project_id={$project->id}")->assertStatus(404);
        $this->getJson("/api/v1/get-project-task?task_id={$task->id}")->assertStatus(404);
        $this->postJson('/api/v1/update-project', ['project_id' => $project->id, 'project_name' => 'Hacked'])->assertStatus(404);
        $this->postJson('/api/v1/update-project-task', ['task_id' => $task->id, 'name' => 'Hacked', 'estimated_hrs' => 1, 'priority' => 'low', 'stage_id' => 0])->assertStatus(404);
        $this->postJson('/api/v1/duplicate-project', ['project_id' => $project->id])->assertStatus(404);
        $this->postJson('/api/v1/delete-project-task', ['task_id' => $task->id])->assertStatus(404);
        $this->postJson('/api/v1/delete-project', ['project_id' => $project->id])->assertStatus(404);
        $this->assertNotNull(Project::find($project->id));
        $this->assertSame('BRF task', $task->fresh()->name);
    }

    // ---------------------------------------------------------------- shadowed routes

    public function test_static_routes_are_no_longer_shadowed_by_id_routes()
    {
        Sanctum::actingAs($this->company);
        $router = app('router')->getRoutes();

        foreach ([
            '/api/v1/trainings/types' => 'getTrainingTypes',
            '/api/v1/trainings/trainers' => 'getTrainers',
            '/api/v1/jobs/categories' => 'getJobCategories',
            '/api/v1/attendance/export' => 'exportAttendance',
        ] as $url => $method) {
            $this->assertStringEndsWith('@' . $method, $router->match(\Illuminate\Http\Request::create($url, 'GET'))->getActionName(), $url);
        }
    }
}
