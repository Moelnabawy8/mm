-- ============================================================
--  لماذا تسقط تاسكات من Task Report - استعلامات تشخيصية
--  شغّلها على قاعدة بيانات السيرفر (erp_newerp)
-- ============================================================

-- (1) هل أي مرحلة عليها علامة "مكتملة"؟  المتوقع: 0
--     التقرير يبحث أولاً عن complete = 1، وهذا العمود لا يُكتب في أي مكان بالنظام.
SELECT COUNT(*) AS stages_marked_complete
FROM task_stages
WHERE complete = 1;


-- (2) كل المراحل الموجودة، وكم تاسك في كل واحدة.
--     العمود matches_report يوضح أي مرحلة يعتبرها التقرير "منجزة"
--     (يطابق بالاسم الإنجليزي فقط: done / complete / finished)
SELECT
    s.id,
    s.name                                        AS stage_name,
    s.`order`,
    s.complete                                    AS complete_flag,
    (s.name LIKE '%done%'
     OR s.name LIKE '%complete%'
     OR s.name LIKE '%finished%')                 AS matches_report,
    COUNT(t.id)                                   AS tasks_in_stage,
    SUM(t.is_complete = 1)                        AS marked_done_by_app
FROM task_stages s
LEFT JOIN project_tasks t ON t.stage_id = s.id
GROUP BY s.id, s.name, s.`order`, s.complete
ORDER BY s.created_by, s.`order`;


-- (3) *** الأهم *** التاسكات المسقطة:
--     النظام يعتبرها منجزة (is_complete = 1) لكن التقرير يعدّها "قيد التنفيذ"
--     لأن اسم مرحلتها ليس done / complete / finished.
SELECT COUNT(*) AS finished_but_counted_as_pending
FROM project_tasks t
LEFT JOIN task_stages s ON s.id = t.stage_id
WHERE t.is_complete = 1
  AND COALESCE(s.name, '') NOT LIKE '%done%'
  AND COALESCE(s.name, '') NOT LIKE '%complete%'
  AND COALESCE(s.name, '') NOT LIKE '%finished%';


-- (4) نفس الشيء لكن موزّعاً على المراحل، لمعرفة أي مرحلة تبتلع التاسكات
SELECT
    COALESCE(s.name, '(بدون مرحلة)') AS stage_name,
    COUNT(*)                          AS finished_but_counted_as_pending
FROM project_tasks t
LEFT JOIN task_stages s ON s.id = t.stage_id
WHERE t.is_complete = 1
  AND COALESCE(s.name, '') NOT LIKE '%done%'
  AND COALESCE(s.name, '') NOT LIKE '%complete%'
  AND COALESCE(s.name, '') NOT LIKE '%finished%'
GROUP BY s.name
ORDER BY finished_but_counted_as_pending DESC;


-- (5) العكس: التقرير يعدّها منجزة والنظام لا - بسبب خطأ "آخر مرحلة" عند السحب
SELECT COUNT(*) AS in_done_stage_but_flag_is_zero
FROM project_tasks t
JOIN task_stages s ON s.id = t.stage_id
WHERE t.is_complete = 0
  AND (s.name LIKE '%done%' OR s.name LIKE '%complete%' OR s.name LIKE '%finished%');


-- (6) تاسكات بمرحلة غير موجودة أصلاً (مرحلة محذوفة) - تظهر باسم "-"
SELECT COUNT(*) AS tasks_with_missing_stage
FROM project_tasks t
LEFT JOIN task_stages s ON s.id = t.stage_id
WHERE s.id IS NULL;


-- (7) تاسكات بلا تاريخ استحقاق - تختفي فوراً عند اختيار أي فترة زمنية
--     لأن الفلتر الافتراضي يعمل على end_date
SELECT
    COUNT(*)                                                   AS total_tasks,
    SUM(end_date IS NULL OR end_date = '0000-00-00')           AS no_due_date,
    SUM(marked_at IS NULL OR marked_at = '0000-00-00')         AS no_completion_date
FROM project_tasks;
