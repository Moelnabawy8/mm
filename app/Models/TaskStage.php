<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskStage extends Model
{
    protected $fillable = [
        'name',
        'complete',
        'project_id',
        'color',
        'order',
        'created_by',
    ];

    public static $stages = [
        "Todo",
        "In Progress",
        "Review",
        "Done",
    ];

    /**
     * Stage ids that mean "this task is finished", for one company.
     *
     * The `complete` flag is the real answer. The two fallbacks only cover
     * companies whose stages were created before the flag could be set from
     * the UI, and can be dropped once every company has flagged a stage.
     *
     * Everything that decides whether a task is done - the board, the
     * complete toggle and the task report - goes through here, so the answer
     * cannot drift between them again.
     */
    public static function completedIds($createdBy): array
    {
        $ids = self::where('created_by', $createdBy)->where('complete', 1)->pluck('id')->toArray();

        if (empty($ids)) {
            $ids = self::where('created_by', $createdBy)
                ->where(function ($q) {
                    $q->where('name', 'LIKE', '%done%')
                        ->orWhere('name', 'LIKE', '%complete%')
                        ->orWhere('name', 'LIKE', '%finished%');
                })
                ->pluck('id')->toArray();
        }

        if (empty($ids)) {
            $last = self::where('created_by', $createdBy)->orderBy('order', 'DESC')->first();
            $ids  = $last ? [$last->id] : [];
        }

        return array_map('intval', $ids);
    }

    /**
     * Where a task lands when it is ticked complete: the first completion
     * stage by board order, so a board with more than one "done" column
     * behaves predictably.
     */
    public static function completionStage($createdBy)
    {
        $ids = self::completedIds($createdBy);

        return empty($ids) ? null : self::whereIn('id', $ids)->orderBy('order')->first();
    }

    /** Where a task returns to when its completion is undone. */
    public static function reopenStage($createdBy)
    {
        $completed = self::completedIds($createdBy);

        return self::where('created_by', $createdBy)
            ->whereNotIn('id', empty($completed) ? [0] : $completed)
            ->orderBy('order')
            ->first();
    }

    public static function getChartData()
    {
        $usr     = \Auth::user();
        $m       = date("m");
        $de      = date("d");
        $y       = date("Y");
        $format  = 'Y-m-d';
        $arrDate = [];
        $arrDay  = [];

        for($i = 0; $i <= 7 - 1; $i++)
        {
            $date              = date($format, mktime(0, 0, 0, $m, ($de - $i), $y));
            $arrDay['label'][] = __(date('D', mktime(0, 0, 0, $m, ($de - $i), $y)));
            $arrDate[]         = $date;
        }

        $stages  = TaskStage::where('created_by', '=', $usr->creatorId())->get();
        $arrTask = [];

        $i = 0;
        if($usr->type == 'company')
        {
            foreach($stages as $key => $stage)
            {
                $data = [];
                foreach($arrDate as $d)
                {
                    $data[] = ProjectTask::where('stage_id', '=', $stage->id)->whereDate('updated_at', '=', $d)->count();
                }

                $dataset['name']           = $stage->name;
                $dataset['backgroundColor'] = 'transparent';
                $dataset['borderColor']     = $stage->color;
                $dataset['data']            = $data;
                $arrTask[]                  = $dataset;
                $i++;
            }

            $arrTaskData = array_merge($arrDay, ['dataset' => $arrTask]);
            unset($arrTaskData['dataset'][$i - 1]['fill']);
            $arrTaskData['dataset'][$i - 1]['backgroundColor'] = '#ccc';

            return $arrTaskData;
        }
        elseif($usr->type == 'client')
        {
            foreach($stages as $key => $stage)
            {
                $data = [];
                foreach($arrDate as $d)
                {
                    $data[] = ProjectTask::join('projects', 'project_tasks.project_id', '=', 'projects.id')
                        ->where('projects.client_id', '=', $usr->id)
                        ->where('stage_id', '=', $stage->id)
                        ->whereDate('project_tasks.updated_at', '=', $d)->count();
                }

                $dataset['name']           = $stage->name;
                $dataset['backgroundColor'] = 'transparent';
                $dataset['borderColor']     = $stage->color;
                $dataset['data']            = $data;
                $arrTask[]                  = $dataset;
                $i++;
            }
            $arrTaskData = array_merge($arrDay, ['dataset' => $arrTask]);
            unset($arrTaskData['dataset'][$i - 1]['fill']);
            $arrTaskData['dataset'][$i - 1]['backgroundColor'] = '#ccc';

            return $arrTaskData;
        }
        else
        {
            foreach($stages as $key => $stage)
            {
                $data = [];
                foreach($arrDate as $d)
                {
                    $data[] = ProjectTask::where('assign_to', '=', $usr->id)->where('stage_id', '=', $stage->id)->whereDate('project_tasks.updated_at', '=', $d)->count();
                }


                $dataset['name']           = $stage->name;
                $dataset['backgroundColor'] = 'transparent';
                $dataset['borderColor']     = $stage->color;
                $dataset['data']            = $data;
                $arrTask[]                  = $dataset;
                $i++;
            }
            $arrTaskData = array_merge($arrDay, ['dataset' => $arrTask]);
            unset($arrTaskData['dataset'][$i - 1]['fill']);
            $arrTaskData['dataset'][$i - 1]['backgroundColor'] = '#ccc';

            return $arrTaskData;
        }
    }
}
