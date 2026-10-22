<?php
/**
 * Anchored in-place patch.
 *
 * Unlike the leads deploy scripts this one does NOT ship whole files: the deals
 * files on the server are ahead of the local checkout, so replacing them would
 * throw away whatever is only on the server. Each change is an exact find ->
 * replace pair instead, and the script refuses to touch anything unless every
 * anchor is found exactly once.
 *
 *   php apply_support_lead_picker_v1.php              dry run, writes nothing
 *   php apply_support_lead_picker_v1.php --apply      back up, then apply everything
 *   php apply_support_lead_picker_v1.php --rollback   restore the newest backup
 *
 * Exit codes: 0 fine, 2 an anchor or a preflight check failed (nothing written),
 * 3 the backup could not be taken, 4 a write failed (everything undone),
 * 5 a changed class no longer loads (everything undone).
 */

$BANNER = 'apply_support_lead_picker v1  (three support-request screens rendered every lead as an <option>)';
$edits = array (
  0 => 
  array (
    'path' => 'resources/views/deals/_lead_picker.blade.php',
    'label' => 'picker search url param',
    'find' => 'ICAgICRyZXF1aXJlZCA9ICRyZXF1aXJlZCA/PyBmYWxzZTsKQGVuZHBocA==',
    'replace' => 'ICAgICRyZXF1aXJlZCA9ICRyZXF1aXJlZCA/PyBmYWxzZTsKICAgIC8vIERlZmF1bHRzIHRvIHRoZSBkZWFscyBlbmRwb2ludCwgc28gZXZlcnkgY2FsbGVyIHdyaXR0ZW4gYmVmb3JlIHRoaXMgc3RheXMKICAgIC8vIGFzIGl0IHdhcy4gT3RoZXIgbW9kdWxlcyBwYXNzIHRoZWlyIG93biwgc2NvcGVkIHRvIHRoZWlyIG93biBwZXJtaXNzaW9ucy4KICAgICRzZWFyY2hVcmwgPSAkc2VhcmNoVXJsID8/IHJvdXRlKCdkZWFscy5sZWFkcy5zZWFyY2gnKTsKQGVuZHBocA==',
  ),
  1 => 
  array (
    'path' => 'resources/views/deals/_lead_picker.blade.php',
    'label' => 'picker uses that url',
    'find' => 'ICAgIHdpbmRvdy5ERUFMX0xFQURfU0VBUkNIX1VSTCA9ICJ7eyByb3V0ZSgnZGVhbHMubGVhZHMuc2VhcmNoJykgfX0iOw==',
    'replace' => 'ICAgIHdpbmRvdy5ERUFMX0xFQURfU0VBUkNIX1VSTCA9ICJ7eyAkc2VhcmNoVXJsIH19Ijs=',
  ),
  2 => 
  array (
    'path' => 'routes/web.php',
    'label' => 'search route',
    'find' => 'Um91dGU6OnJlc291cmNlKCdzdXBwb3J0LXJlcXVlc3RzJywgU3VwcG9ydFJlcXVlc3RDb250cm9sbGVyOjpjbGFzcyktPm1pZGRsZXdhcmUoWydhdXRoJywgJ1hTUyddKTs=',
    'replace' => 'Ly8gQSBwYXRoIG9mIGl0cyBvd24gcmF0aGVyIHRoYW4gc3VwcG9ydC1yZXF1ZXN0cy8uLi4sIHNvIHRoZSByZXNvdXJjZSByb3V0ZSdzCi8vIHtzdXBwb3J0X3JlcXVlc3R9IHBhcmFtZXRlciBjYW4gbmV2ZXIgc3dhbGxvdyBpdC4KUm91dGU6OmdldCgnc3VwcG9ydC1yZXF1ZXN0cy1sZWFkcy1zZWFyY2gnLCBbU3VwcG9ydFJlcXVlc3RDb250cm9sbGVyOjpjbGFzcywgJ2xlYWRzU2VhcmNoJ10pCiAgICAtPm5hbWUoJ3N1cHBvcnQtcmVxdWVzdHMubGVhZHMuc2VhcmNoJyktPm1pZGRsZXdhcmUoWydhdXRoJywgJ1hTUyddKTsKUm91dGU6OnJlc291cmNlKCdzdXBwb3J0LXJlcXVlc3RzJywgU3VwcG9ydFJlcXVlc3RDb250cm9sbGVyOjpjbGFzcyktPm1pZGRsZXdhcmUoWydhdXRoJywgJ1hTUyddKTs=',
  ),
  3 => 
  array (
    'path' => 'app/Http/Controllers/SupportRequestController.php',
    'label' => 'index stops loading every lead',
    'find' => 'ICAgICAgICAgICAgJGxlYWRzID0gTGVhZDo6d2hlcmUoJ2NyZWF0ZWRfYnknLCBBdXRoOjp1c2VyKCktPm93bmVySWQoKSktPmdldCgpOwoKICAgICAgICAgICAgaWYgKCRyZXF1ZXN0LT5hamF4KCkpIHsKICAgICAgICAgICAgICAgIHJldHVybiB2aWV3KCdzdXBwb3J0LXJlcXVlc3RzLl90YWJsZScsIGNvbXBhY3QoJ3N1cHBvcnRSZXF1ZXN0cycsICdzdGF0cycpKTsKICAgICAgICAgICAgfQoKICAgICAgICAgICAgcmV0dXJuIHZpZXcoJ3N1cHBvcnQtcmVxdWVzdHMuaW5kZXgnLCBjb21wYWN0KCdzdXBwb3J0UmVxdWVzdHMnLCAnc3RhdHMnLCAndGVhbW1hdGVzJywgJ2xlYWRzJykpOw==',
    'replace' => 'ICAgICAgICAgICAgLy8gVGhlIExlYWQvQ2xpZW50IGZpbHRlciB1c2VkIHRvIGJlIGJ1aWx0IGZyb20gZXZlcnkgbGVhZCBvZiB0aGUKICAgICAgICAgICAgLy8gY29tcGFueS4gSXQgc2VhcmNoZXMgdGhlIHNlcnZlciBub3csIHNvIG5vdGhpbmcgaXMgbG9hZGVkIGhlcmUuCgogICAgICAgICAgICBpZiAoJHJlcXVlc3QtPmFqYXgoKSkgewogICAgICAgICAgICAgICAgcmV0dXJuIHZpZXcoJ3N1cHBvcnQtcmVxdWVzdHMuX3RhYmxlJywgY29tcGFjdCgnc3VwcG9ydFJlcXVlc3RzJywgJ3N0YXRzJykpOwogICAgICAgICAgICB9CgogICAgICAgICAgICByZXR1cm4gdmlldygnc3VwcG9ydC1yZXF1ZXN0cy5pbmRleCcsIGNvbXBhY3QoJ3N1cHBvcnRSZXF1ZXN0cycsICdzdGF0cycsICd0ZWFtbWF0ZXMnKSk7',
  ),
  4 => 
  array (
    'path' => 'app/Http/Controllers/SupportRequestController.php',
    'label' => 'create stops loading every lead',
    'find' => 'ICAgICAgICAgICAgLy8gRmlsdGVyIGxlYWRzIGJhc2VkIG9uIHVzZXIgcm9sZQogICAgICAgICAgICBpZiAoCiAgICAgICAgICAgICAgICAkdXNyLT5oYXNSb2xlKCdTYWxlcyBFbXBsb3llZScpIHx8ICR1c3ItPmhhc1JvbGUoJ3NhbGVzIGVtcGxveWVlJykgfHwKICAgICAgICAgICAgICAgICR1c3ItPmhhc1JvbGUoJ0FsYWEsU2FiYSxhYmVyJykgfHwgJHVzci0+aGFzUm9sZSgnZmFkaScpCiAgICAgICAgICAgICkgewogICAgICAgICAgICAgICAgLy8gR2V0IGxlYWRzIGFzc2lnbmVkIHRvIHRoaXMgdXNlcgogICAgICAgICAgICAgICAgJHVzZXJMZWFkSWRzID0gXEFwcFxNb2RlbHNcVXNlckxlYWQ6OndoZXJlKCd1c2VyX2lkJywgJHVzci0+aWQpLT5wbHVjaygnbGVhZF9pZCcpOwogICAgICAgICAgICAgICAgJGxlYWRzID0gTGVhZDo6d2hlcmUoJ2NyZWF0ZWRfYnknLCAkdXNyLT5vd25lcklkKCkpCiAgICAgICAgICAgICAgICAgICAgLT53aGVyZUluKCdpZCcsICR1c2VyTGVhZElkcykKICAgICAgICAgICAgICAgICAgICAtPmdldCgpOwogICAgICAgICAgICB9IGVsc2UgewogICAgICAgICAgICAgICAgJGxlYWRzID0gTGVhZDo6d2hlcmUoJ2NyZWF0ZWRfYnknLCAkdXNyLT5vd25lcklkKCkpLT5nZXQoKTsKICAgICAgICAgICAgfQogICAgICAgICAgICAkbGVhZElkID0gJHJlcXVlc3QtPnF1ZXJ5KCdsZWFkX2lkJyk7CgogICAgICAgICAgICByZXR1cm4gdmlldygnc3VwcG9ydC1yZXF1ZXN0cy5jcmVhdGUnLCBjb21wYWN0KCd0ZWFtbWF0ZXMnLCAnbGVhZHMnLCAnbGVhZElkJykpOw==',
    'replace' => 'ICAgICAgICAgICAgLy8gT25seSB0aGUgbGVhZCB0aGF0IHdhcyBhc2tlZCBmb3IgaXMgbG9hZGVkLiBUaGlzIHVzZWQgdG8gZmV0Y2ggZXZlcnkKICAgICAgICAgICAgLy8gbGVhZCB0aGUgdXNlciBtYXkgc2VlIC0tIHRlbnMgb2YgdGhvdXNhbmRzIG9mIHJvd3MgdHVybmVkIGludG8KICAgICAgICAgICAgLy8gPG9wdGlvbj4gZWxlbWVudHMsIGVhY2ggb2Ygd2hpY2ggQ2hvaWNlcy5qcyB0aGVuIHdyYXBwZWQgaW4gaXRzIG93bgogICAgICAgICAgICAvLyBET00gbm9kZS4gVGhlIHNhbWUgc2hhcGUgbG9ja2VkIHRoZSBicm93c2VyIHVwIG9uIC9kZWFscyB1bnRpbCB0aGF0CiAgICAgICAgICAgIC8vIHBhZ2Ugd2FzIGNvbnZlcnRlZCB0byB0aGlzIHBpY2tlci4KICAgICAgICAgICAgJGxlYWRJZCA9ICRyZXF1ZXN0LT5xdWVyeSgnbGVhZF9pZCcpOwogICAgICAgICAgICAkc2VsZWN0ZWRMZWFkID0gJGxlYWRJZCA/ICR0aGlzLT52aXNpYmxlTGVhZHNRdWVyeSgkdXNyKS0+ZmluZCgkbGVhZElkKSA6IG51bGw7CiAgICAgICAgICAgICRzZWxlY3RlZExlYWROYW1lID0gJHNlbGVjdGVkTGVhZCA/ICRzZWxlY3RlZExlYWQtPm5hbWUgOiAnJzsKCiAgICAgICAgICAgIHJldHVybiB2aWV3KCdzdXBwb3J0LXJlcXVlc3RzLmNyZWF0ZScsIGNvbXBhY3QoJ3RlYW1tYXRlcycsICdsZWFkSWQnLCAnc2VsZWN0ZWRMZWFkTmFtZScpKTs=',
  ),
  5 => 
  array (
    'path' => 'app/Http/Controllers/SupportRequestController.php',
    'label' => 'edit stops loading every lead',
    'find' => 'ICAgICAgICAgICAgJGxlYWRzID0gTGVhZDo6d2hlcmUoJ2NyZWF0ZWRfYnknLCBBdXRoOjp1c2VyKCktPm93bmVySWQoKSktPmdldCgpOwogICAgICAgICAgICAkbGVhZElkID0gJHJlcXVlc3QtPnF1ZXJ5KCdsZWFkX2lkJyk7CgogICAgICAgICAgICByZXR1cm4gdmlldygnc3VwcG9ydC1yZXF1ZXN0cy5lZGl0JywgY29tcGFjdCgnc3VwcG9ydFJlcXVlc3QnLCAndGVhbW1hdGVzJywgJ2xlYWRzJywgJ2xlYWRJZCcpKTs=',
    'replace' => 'ICAgICAgICAgICAgLy8gc2VlIHRoZSBub3RlIGluIGNyZWF0ZSgpOiBvbmUgbGVhZCwgbm90IHRoZSB3aG9sZSB0YWJsZQogICAgICAgICAgICAkbGVhZElkID0gJHJlcXVlc3QtPnF1ZXJ5KCdsZWFkX2lkJyk7CiAgICAgICAgICAgICRjdXJyZW50TGVhZCA9IExlYWQ6OndoZXJlKCdjcmVhdGVkX2J5JywgQXV0aDo6dXNlcigpLT5vd25lcklkKCkpCiAgICAgICAgICAgICAgICAtPmZpbmQoJHN1cHBvcnRSZXF1ZXN0LT5sZWFkX2lkKTsKICAgICAgICAgICAgJHNlbGVjdGVkTGVhZE5hbWUgPSAkY3VycmVudExlYWQgPyAkY3VycmVudExlYWQtPm5hbWUgOiAnJzsKCiAgICAgICAgICAgIHJldHVybiB2aWV3KCdzdXBwb3J0LXJlcXVlc3RzLmVkaXQnLCBjb21wYWN0KCdzdXBwb3J0UmVxdWVzdCcsICd0ZWFtbWF0ZXMnLCAnbGVhZElkJywgJ3NlbGVjdGVkTGVhZE5hbWUnKSk7',
  ),
  6 => 
  array (
    'path' => 'app/Http/Controllers/SupportRequestController.php',
    'label' => 'visibility helper and search',
    'find' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiBzdG9yZShSZXF1ZXN0ICRyZXF1ZXN0KQogICAgewogICAgICAgIGlmIChBdXRoOjp1c2VyKCktPmNhbignY3JlYXRlIHN1cHBvcnQgcmVxdWVzdCcpKSB7',
    'replace' => 'ICAgIC8qKgogICAgICogVGhlIGxlYWRzIGEgdXNlciBpcyBhbGxvd2VkIHRvIGF0dGFjaCBhIHN1cHBvcnQgcmVxdWVzdCB0bzogc2FsZXMgcGVvcGxlCiAgICAgKiBzZWUgdGhlIG9uZXMgYXNzaWduZWQgdG8gdGhlbSwgZXZlcnlvbmUgZWxzZSBzZWVzIHRoZSBjb21wYW55J3MuCiAgICAgKgogICAgICogTGlmdGVkIG91dCBvZiBjcmVhdGUoKSBzbyB0aGUgcGlja2VyJ3Mgc2VhcmNoIGVuZHBvaW50IGNhbm5vdCBkcmlmdCBhd2F5CiAgICAgKiBmcm9tIHRoZSBydWxlIHRoZSBmb3JtIGl0c2VsZiBhcHBsaWVzLgogICAgICovCiAgICBwcml2YXRlIGZ1bmN0aW9uIHZpc2libGVMZWFkc1F1ZXJ5KCR1c3IpCiAgICB7CiAgICAgICAgJHF1ZXJ5ID0gTGVhZDo6d2hlcmUoJ2NyZWF0ZWRfYnknLCAkdXNyLT5vd25lcklkKCkpOwoKICAgICAgICBpZiAoCiAgICAgICAgICAgICR1c3ItPmhhc1JvbGUoJ1NhbGVzIEVtcGxveWVlJykgfHwgJHVzci0+aGFzUm9sZSgnc2FsZXMgZW1wbG95ZWUnKSB8fAogICAgICAgICAgICAkdXNyLT5oYXNSb2xlKCdBbGFhLFNhYmEsYWJlcicpIHx8ICR1c3ItPmhhc1JvbGUoJ2ZhZGknKQogICAgICAgICkgewogICAgICAgICAgICAkcXVlcnktPndoZXJlSW4oJ2lkJywgXEFwcFxNb2RlbHNcVXNlckxlYWQ6OndoZXJlKCd1c2VyX2lkJywgJHVzci0+aWQpLT5wbHVjaygnbGVhZF9pZCcpKTsKICAgICAgICB9CgogICAgICAgIHJldHVybiAkcXVlcnk7CiAgICB9CgogICAgLyoqCiAgICAgKiBGZWVkcyB0aGUgbGVhZCBwaWNrZXIgb24gdGhlIGluZGV4IGZpbHRlciBhbmQgYm90aCBmb3Jtcy4gUmV0dXJucyBhdCBtb3N0CiAgICAgKiAyNSByb3dzLCB3aGljaCBpcyB0aGUgd2hvbGUgcG9pbnQ6IHRoZSB0aHJlZSA8c2VsZWN0PiBlbGVtZW50cyBpdCByZXBsYWNlZAogICAgICogaGVsZCBvbmUgPG9wdGlvbj4gcGVyIGxlYWQgaW4gdGhlIGNvbXBhbnkuCiAgICAgKi8KICAgIHB1YmxpYyBmdW5jdGlvbiBsZWFkc1NlYXJjaChSZXF1ZXN0ICRyZXF1ZXN0KQogICAgewogICAgICAgICR1c3IgPSBBdXRoOjp1c2VyKCk7CgogICAgICAgIGlmICgKICAgICAgICAgICAgISR1c3ItPmNhbignbWFuYWdlIHN1cHBvcnQgcmVxdWVzdCcpICYmICEkdXNyLT5jYW4oJ2NyZWF0ZSBzdXBwb3J0IHJlcXVlc3QnKQogICAgICAgICAgICAmJiAhJHVzci0+Y2FuKCdlZGl0IHN1cHBvcnQgcmVxdWVzdCcpCiAgICAgICAgKSB7CiAgICAgICAgICAgIHJldHVybiByZXNwb25zZSgpLT5qc29uKFtdLCA0MDEpOwogICAgICAgIH0KCiAgICAgICAgJHF1ZXJ5ID0gJHRoaXMtPnZpc2libGVMZWFkc1F1ZXJ5KCR1c3IpOwoKICAgICAgICAkdGVybSA9IHRyaW0oKHN0cmluZykgJHJlcXVlc3QtPmdldCgncScpKTsKICAgICAgICBpZiAoJHRlcm0gIT09ICcnKSB7CiAgICAgICAgICAgICRxdWVyeS0+d2hlcmUoZnVuY3Rpb24gKCRxKSB1c2UgKCR0ZXJtKSB7CiAgICAgICAgICAgICAgICAkcS0+d2hlcmUoJ25hbWUnLCAnbGlrZScsICclJyAuICR0ZXJtIC4gJyUnKQogICAgICAgICAgICAgICAgICAgIC0+b3JXaGVyZSgncGhvbmUnLCAnbGlrZScsICclJyAuICR0ZXJtIC4gJyUnKQogICAgICAgICAgICAgICAgICAgIC0+b3JXaGVyZSgnZW1haWwnLCAnbGlrZScsICclJyAuICR0ZXJtIC4gJyUnKTsKICAgICAgICAgICAgfSk7CiAgICAgICAgfQoKICAgICAgICAkbGVhZHMgPSAkcXVlcnktPm9yZGVyQnkoJ2lkJywgJ2Rlc2MnKS0+bGltaXQoMjUpLT5nZXQoWydpZCcsICduYW1lJywgJ3Bob25lJ10pOwoKICAgICAgICAvLyBuYW1lIGFuZCBwaG9uZSB0cmF2ZWwgYXBhcnQgc28gdGhlIHBpY2tlciBjYW4gcHV0IHRoZW0gb24gc2VwYXJhdGUKICAgICAgICAvLyBsaW5lczsgam9pbmVkIGludG8gb25lIHN0cmluZyBhbiBBcmFiaWMgbmFtZSBhbmQgYSBMYXRpbi1kaWdpdCBwaG9uZQogICAgICAgIC8vIHN3YXAgcm91bmQgYW5kIHJlYWQgYXMgaWYgdGhlIG51bWJlciBjYW1lIGZpcnN0LgogICAgICAgIHJldHVybiByZXNwb25zZSgpLT5qc29uKAogICAgICAgICAgICAkbGVhZHMtPm1hcChmdW5jdGlvbiAoJGxlYWQpIHsKICAgICAgICAgICAgICAgIHJldHVybiBbCiAgICAgICAgICAgICAgICAgICAgJ2lkJyA9PiAkbGVhZC0+aWQsCiAgICAgICAgICAgICAgICAgICAgJ25hbWUnID0+ICRsZWFkLT5uYW1lLAogICAgICAgICAgICAgICAgICAgICdwaG9uZScgPT4gKHN0cmluZykgJGxlYWQtPnBob25lLAogICAgICAgICAgICAgICAgICAgICd0ZXh0JyA9PiAkbGVhZC0+bmFtZSAuICgkbGVhZC0+cGhvbmUgPyAnIC0gJyAuICRsZWFkLT5waG9uZSA6ICcnKSwKICAgICAgICAgICAgICAgIF07CiAgICAgICAgICAgIH0pLT52YWx1ZXMoKQogICAgICAgICk7CiAgICB9CgogICAgcHVibGljIGZ1bmN0aW9uIHN0b3JlKFJlcXVlc3QgJHJlcXVlc3QpCiAgICB7CiAgICAgICAgaWYgKEF1dGg6OnVzZXIoKS0+Y2FuKCdjcmVhdGUgc3VwcG9ydCByZXF1ZXN0JykpIHs=',
  ),
  7 => 
  array (
    'path' => 'resources/views/support-requests/create.blade.php',
    'label' => 'create form picker',
    'find' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPGxhYmVsIGZvcj0ibGVhZF9pZCIgY2xhc3M9ImZvcm0tbGFiZWwgcmVxdWlyZWQiPnt7IF9fKCdMZWFkL0NsaWVudCcpIH19PC9sYWJlbD4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPHNlbGVjdCBjbGFzcz0iZm9ybS1jb250cm9sIHNlbGVjdDIiIGlkPSJsZWFkX2lkIiBuYW1lPSJsZWFkX2lkIiByZXF1aXJlZD4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxvcHRpb24gdmFsdWU9IiI+e3sgX18oJ1NlbGVjdCBMZWFkL0NsaWVudCcpIH19PC9vcHRpb24+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBAZm9yZWFjaCAoJGxlYWRzIGFzICRsZWFkKQogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxvcHRpb24gdmFsdWU9Int7ICRsZWFkLT5pZCB9fSIKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAge3sgaXNzZXQoJGxlYWRJZCkgJiYgJGxlYWRJZCA9PSAkbGVhZC0+aWQgPyAnc2VsZWN0ZWQnIDogJycgfX0+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHt7ICRsZWFkLT5uYW1lIH19IC0ge3sgJGxlYWQtPnBob25lID8/ICdOL0EnIH19CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPC9vcHRpb24+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBAZW5kZm9yZWFjaAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8L3NlbGVjdD4=',
    'replace' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPGxhYmVsIGZvcj0ibGVhZF9pZCIgY2xhc3M9ImZvcm0tbGFiZWwgcmVxdWlyZWQiPnt7IF9fKCdMZWFkL0NsaWVudCcpIH19PC9sYWJlbD4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgQGluY2x1ZGUoJ2RlYWxzLl9sZWFkX3BpY2tlcicsIFsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICdwaWNrZXJJZCcgPT4gJ2xlYWRfaWQnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ2ZpZWxkTmFtZScgPT4gJ2xlYWRfaWQnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ3NlbGVjdGVkSWQnID0+ICRsZWFkSWQgPz8gbnVsbCwKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICdzZWxlY3RlZE5hbWUnID0+ICRzZWxlY3RlZExlYWROYW1lID8/ICcnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ3JlcXVpcmVkJyA9PiB0cnVlLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ3NlYXJjaFVybCcgPT4gcm91dGUoJ3N1cHBvcnQtcmVxdWVzdHMubGVhZHMuc2VhcmNoJyksCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIF0p',
  ),
  8 => 
  array (
    'path' => 'resources/views/support-requests/edit.blade.php',
    'label' => 'edit form picker',
    'find' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxsYWJlbCBmb3I9ImxlYWRfaWQiIGNsYXNzPSJmb3JtLWxhYmVsIHJlcXVpcmVkIj57eyBfXygnTGVhZC9DbGllbnQnKSB9fTwvbGFiZWw+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8c2VsZWN0IGNsYXNzPSJmb3JtLWNvbnRyb2wgc2VsZWN0MiIgaWQ9ImxlYWRfaWQiIG5hbWU9ImxlYWRfaWQiIHJlcXVpcmVkPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxvcHRpb24gdmFsdWU9IiI+e3sgX18oJ1NlbGVjdCBMZWFkL0NsaWVudCcpIH19PC9vcHRpb24+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgQGZvcmVhY2ggKCRsZWFkcyBhcyAkbGVhZCkKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPG9wdGlvbiB2YWx1ZT0ie3sgJGxlYWQtPmlkIH19IgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAge3sgJHN1cHBvcnRSZXF1ZXN0LT5sZWFkX2lkID09ICRsZWFkLT5pZCA/ICdzZWxlY3RlZCcgOiAnJyB9fT4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHt7ICRsZWFkLT5uYW1lIH19IC0ge3sgJGxlYWQtPmVtYWlsID8/ICdOL0EnIH19CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDwvb3B0aW9uPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIEBlbmRmb3JlYWNoCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8L3NlbGVjdD4=',
    'replace' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxsYWJlbCBmb3I9ImxlYWRfaWQiIGNsYXNzPSJmb3JtLWxhYmVsIHJlcXVpcmVkIj57eyBfXygnTGVhZC9DbGllbnQnKSB9fTwvbGFiZWw+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBAaW5jbHVkZSgnZGVhbHMuX2xlYWRfcGlja2VyJywgWwogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICdwaWNrZXJJZCcgPT4gJ2xlYWRfaWQnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICdmaWVsZE5hbWUnID0+ICdsZWFkX2lkJywKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnc2VsZWN0ZWRJZCcgPT4gJHN1cHBvcnRSZXF1ZXN0LT5sZWFkX2lkLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICdzZWxlY3RlZE5hbWUnID0+ICRzZWxlY3RlZExlYWROYW1lID8/ICcnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICdyZXF1aXJlZCcgPT4gdHJ1ZSwKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnc2VhcmNoVXJsJyA9PiByb3V0ZSgnc3VwcG9ydC1yZXF1ZXN0cy5sZWFkcy5zZWFyY2gnKSwKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIF0p',
  ),
  9 => 
  array (
    'path' => 'resources/views/support-requests/index.blade.php',
    'label' => 'index filter picker',
    'find' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPGxhYmVsIGZvcj0iZmlsdGVyX2xlYWQiIGNsYXNzPSJmb3JtLWxhYmVsIj57eyBfXygnTGVhZC9DbGllbnQnKSB9fTwvbGFiZWw+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxzZWxlY3QgY2xhc3M9ImZvcm0tY29udHJvbCBzZWxlY3QyIiBpZD0iZmlsdGVyX2xlYWQiIG5hbWU9ImxlYWRfaWQiPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPG9wdGlvbiB2YWx1ZT0iIj57eyBfXygnQWxsIExlYWRzJykgfX08L29wdGlvbj4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIEBmb3JlYWNoICgkbGVhZHMgYXMgJGxlYWQpCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPG9wdGlvbiB2YWx1ZT0ie3sgJGxlYWQtPmlkIH19Ij57eyAkbGVhZC0+bmFtZSB9fTwvb3B0aW9uPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgQGVuZGZvcmVhY2gKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPC9zZWxlY3Q+',
    'replace' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPGxhYmVsIGZvcj0iZmlsdGVyX2xlYWQiIGNsYXNzPSJmb3JtLWxhYmVsIj57eyBfXygnTGVhZC9DbGllbnQnKSB9fTwvbGFiZWw+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHt7LS0gVGhlIGhpZGRlbiBmaWVsZCBrZWVwcyB0aGUgaWQgI2ZpbHRlcl9sZWFkLCBzbyB0aGUgZmlsdGVyCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgamF2YXNjcmlwdCB0aGF0IHJlYWRzICQoJyNmaWx0ZXJfbGVhZCcpLnZhbCgpIGlzIHVudG91Y2hlZC4gLS19fQogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBAaW5jbHVkZSgnZGVhbHMuX2xlYWRfcGlja2VyJywgWwogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ3BpY2tlcklkJyA9PiAnZmlsdGVyX2xlYWQnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ2ZpZWxkTmFtZScgPT4gJ2xlYWRfaWQnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ3NlbGVjdGVkSWQnID0+IG51bGwsCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnc2VsZWN0ZWROYW1lJyA9PiAnJywKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICdyZXF1aXJlZCcgPT4gZmFsc2UsCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnc2VhcmNoVXJsJyA9PiByb3V0ZSgnc3VwcG9ydC1yZXF1ZXN0cy5sZWFkcy5zZWFyY2gnKSwKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgXSk=',
  ),
  10 => 
  array (
    'path' => 'resources/views/support-requests/index.blade.php',
    'label' => 'filter remembers the lead name',
    'find' => 'ICAgICAgICAgICAgICAgICAgICBpZiAoX3NmLmxlYWRfaWQpICQoJyNmaWx0ZXJfbGVhZCcpLnZhbChfc2YubGVhZF9pZCkudHJpZ2dlcignY2hhbmdlJyk7',
    'replace' => 'ICAgICAgICAgICAgICAgICAgICBpZiAoX3NmLmxlYWRfaWQpIHsKICAgICAgICAgICAgICAgICAgICAgICAgJCgnI2ZpbHRlcl9sZWFkJykudmFsKF9zZi5sZWFkX2lkKS50cmlnZ2VyKCdjaGFuZ2UnKTsKICAgICAgICAgICAgICAgICAgICAgICAgLy8gVGhlIHBpY2tlcidzIHRleHQgYm94IGlzIG5vdCB0aGUgZmllbGQgdGhhdCBpcyBzdWJtaXR0ZWQsCiAgICAgICAgICAgICAgICAgICAgICAgIC8vIHNvIHRoZSBuYW1lIGhhcyB0byBiZSBwdXQgYmFjayBzZXBhcmF0ZWx5IG9yIHRoZSBmaWx0ZXIKICAgICAgICAgICAgICAgICAgICAgICAgLy8gd291bGQgYmUgYXBwbGllZCB3aGlsZSB0aGUgYm94IGxvb2tlZCBlbXB0eS4KICAgICAgICAgICAgICAgICAgICAgICAgaWYgKF9zZi5sZWFkX2xhYmVsKSAkKCcjZmlsdGVyX2xlYWRfc2VhcmNoJykudmFsKF9zZi5sZWFkX2xhYmVsKTsKICAgICAgICAgICAgICAgICAgICB9',
  ),
  11 => 
  array (
    'path' => 'resources/views/support-requests/index.blade.php',
    'label' => 'filter saves the lead name',
    'find' => 'ICAgICAgICAgICAgICAgIGxlYWRfaWQ6ICQoJyNmaWx0ZXJfbGVhZCcpLnZhbCgpLA==',
    'replace' => 'ICAgICAgICAgICAgICAgIGxlYWRfaWQ6ICQoJyNmaWx0ZXJfbGVhZCcpLnZhbCgpLAogICAgICAgICAgICAgICAgbGVhZF9sYWJlbDogJCgnI2ZpbHRlcl9sZWFkX3NlYXJjaCcpLnZhbCgpLA==',
  ),
);
$classes = array (
  0 => 'App\\Http\\Controllers\\SupportRequestController',
);
$backupPrefix = 'support-picker';

echo $BANNER . "\n\n";

// ---- locate the Laravel root -----------------------------------------------------

$root = __DIR__;
while (!file_exists($root . '/artisan') && dirname($root) !== $root) {
    $root = dirname($root);
}
if (!file_exists($root . '/artisan')) {
    fwrite(STDERR, "Could not locate the Laravel root (no artisan found).\n");
    exit(2);
}
$root = rtrim(str_replace('\\', '/', $root), '/');

$argvAll = $argv;
$apply = in_array('--apply', $argvAll, true);
$rollback = in_array('--rollback', $argvAll, true);
$simulateFail = null;
foreach ($argvAll as $a) {
    if (strpos($a, '--simulate-write-failure=') === 0) {
        $simulateFail = substr($a, strlen('--simulate-write-failure='));
    }
}

echo "Laravel root: $root\n";
echo 'Running as:   ' . (function_exists('posix_getpwuid') && function_exists('posix_geteuid')
    ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown')
    : (getenv('USERNAME') ?: 'unknown')) . "\n";
echo 'Mode:         ' . ($rollback ? 'rollback' : ($apply ? 'apply' : 'check')) . "\n\n";

function lf($s)
{
    return str_replace("\r\n", "\n", $s);
}

function h16($s)
{
    return substr(hash('sha256', lf($s)), 0, 16);
}

/**
 * The nearest ancestor that exists, so we can report a useful problem for a path
 * whose parent directories are missing.
 */
function nearestExisting($path)
{
    $p = $path;
    while ($p && !file_exists($p) && dirname($p) !== $p) {
        $p = dirname($p);
    }
    return $p;
}

function writeProblem($root, $rel)
{
    $full = $root . '/' . $rel;
    if (!is_file($full)) {
        $near = nearestExisting($full);
        if ($near !== $full && is_file($near)) {
            return 'is not a directory: ' . str_replace($root . '/', '', $near);
        }
        return 'file not found';
    }
    if (!is_readable($full)) {
        return 'not readable';
    }
    if (!is_writable($full)) {
        return 'not writable by this user';
    }
    if (!is_writable(dirname($full))) {
        return 'the directory ' . str_replace($root . '/', '', dirname($full)) . ' is not writable by this user';
    }
    return null;
}

// ---- rollback ---------------------------------------------------------------------

function backupDirs($root, $prefix)
{
    $d = glob($root . '/storage/backups/' . $prefix . '-*', GLOB_ONLYDIR) ?: [];
    sort($d);
    return $d;
}

if ($rollback) {
    $dirs = backupDirs($root, $backupPrefix);
    if (!$dirs) {
        fwrite(STDERR, "No backup to roll back to under storage/backups/$backupPrefix-*.\n");
        exit(2);
    }
    $dir = end($dirs);
    echo "Restoring from $dir\n\n";

    $manifest = @file($dir . '/MANIFEST.txt', FILE_IGNORE_NEW_LINES) ?: [];
    $applied = [];
    foreach ($manifest as $line) {
        $p = preg_split('/\s+/', trim($line));
        if (count($p) >= 3 && $p[0] === 'FILE') {
            // FILE <rel> <hash-before> <hash-after>
            $applied[$p[1]] = ['before' => $p[2], 'after' => isset($p[3]) ? $p[3] : null];
        }
    }

    $kept = 0;
    $restored = 0;
    foreach ($applied as $rel => $h) {
        $full = $root . '/' . $rel;
        $saved = $dir . '/' . $rel;
        if (!is_file($saved)) {
            echo "[SKIP] $rel -- not in the backup\n";
            continue;
        }
        if (is_file($full) && $h['after'] && h16(file_get_contents($full)) !== $h['after']) {
            echo "[KEEP] $rel -- changed since the patch was applied, leaving it alone\n";
            $kept++;
            continue;
        }
        if (@file_put_contents($full, file_get_contents($saved)) === false) {
            fwrite(STDERR, "[FAIL] $rel -- could not restore\n");
            $kept++;
            continue;
        }
        echo "[ OK ] $rel restored\n";
        $restored++;
    }

    echo "\nRestored $restored file(s)" . ($kept ? ", kept $kept" : '') . ".\n";
    echo "Next: php artisan view:clear && php artisan optimize:clear\n";
    exit($kept ? 2 : 0);
}

// ---- plan --------------------------------------------------------------------------

$byPath = [];
foreach ($edits as $e) {
    $byPath[$e['path']][] = $e;
}

$blocked = 0;
$plan = [];      // rel => new content
$before = [];    // rel => original content
$noop = 0;

foreach ($byPath as $rel => $list) {
    $problem = writeProblem($root, $rel);
    if ($problem !== null) {
        echo "[FAIL] $rel -- $problem\n";
        $blocked++;
        continue;
    }

    $raw = file_get_contents($root . '/' . $rel);
    $crlf = strpos($raw, "\r\n") !== false;
    $content = lf($raw);
    $before[$rel] = $raw;

    $changed = 0;
    $already = 0;
    $bad = false;

    foreach ($list as $e) {
        $find = lf(base64_decode($e['find']));
        $repl = lf(base64_decode($e['replace']));
        $nFind = substr_count($content, $find);
        $nRepl = substr_count($content, $repl);

        // A pure insertion keeps the anchor inside its own replacement, so the
        // anchor still matches after the change. For those the replacement being
        // present is what says "already applied"; otherwise the anchor is.
        if (strpos($repl, $find) !== false) {
            if ($nRepl >= 1) {
                $already++;
                continue;
            }
            if ($nFind === 1) {
                $content = str_replace($find, $repl, $content);
                $changed++;
                continue;
            }
            echo '[FAIL] ' . $rel . ' -- anchor "' . $e['label'] . '" found ' . $nFind . ' times, expected exactly 1' . "\n";
            $bad = true;
            continue;
        }

        if ($nFind === 1) {
            $content = str_replace($find, $repl, $content);
            $changed++;
            continue;
        }
        if ($nFind === 0 && $nRepl >= 1) {
            $already++;
            continue;
        }
        echo '[FAIL] ' . $rel . ' -- anchor "' . $e['label'] . '" found ' . $nFind . ' times, expected exactly 1'
            . ($nRepl ? ' (and the replacement is not there either)' : '') . "\n";
        $bad = true;
    }

    if ($bad) {
        $blocked++;
        continue;
    }

    if ($changed === 0) {
        echo "[SKIP] $rel -- already applied ($already change(s))\n";
        $noop++;
        continue;
    }

    if ($crlf) {
        echo "       note: $rel had CRLF line endings; it will be written as LF\n";
    }

    echo "[READY] $rel -- $changed change(s)" . ($already ? ", $already already applied" : '') . "\n";
    $plan[$rel] = $content;
}

echo "\n--------------------------------------------------\n";
echo 'ready: ' . count($plan) . '   blocked: ' . $blocked . "\n";

if ($blocked) {
    echo "\nNOTHING WAS WRITTEN. An anchor did not match, which means the server copy\n";
    echo "of that file is not what this patch was built against. Send the file back\n";
    echo "so the change can be re-made against it.\n";
    exit(2);
}

if (!$plan) {
    echo "\nEverything is already applied.\n";
    exit(0);
}

if (!$apply) {
    echo "\nDry run only. Re-run with --apply to write these files.\n";
    exit(0);
}

// ---- backup --------------------------------------------------------------------------

$stamp = date('Ymd-His');
$dir = $root . '/storage/backups/' . $backupPrefix . '-' . $stamp;

$backupsRoot = $root . '/storage/backups';
$near = nearestExisting($backupsRoot);
if (is_file($near)) {
    fwrite(STDERR, "The backup location $backupsRoot is blocked by a file at $near.\n");
    exit(3);
}
if (!is_dir($backupsRoot) && !@mkdir($backupsRoot, 0775, true)) {
    fwrite(STDERR, "Could not create the backup location $backupsRoot.\n");
    exit(3);
}
if (!is_writable($backupsRoot)) {
    fwrite(STDERR, "The backup location $backupsRoot is not writable by this user.\n");
    exit(3);
}
if (!@mkdir($dir, 0775, true)) {
    fwrite(STDERR, "Could not create $dir.\n");
    exit(3);
}

echo "Backing up to $dir\n";
$manifestLines = [];
foreach ($plan as $rel => $new) {
    $target = $dir . '/' . $rel;
    if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0775, true)) {
        fwrite(STDERR, "Could not create " . dirname($target) . ".\n");
        exit(3);
    }
    if (@file_put_contents($target, $before[$rel]) === false) {
        fwrite(STDERR, "Could not write the backup copy of $rel.\n");
        exit(3);
    }
    if (h16(file_get_contents($target)) !== h16($before[$rel])) {
        fwrite(STDERR, "The backup copy of $rel does not match the original.\n");
        exit(3);
    }
    echo "       saved $rel\n";
    $manifestLines[] = 'FILE ' . $rel . ' ' . h16($before[$rel]) . ' ' . h16($new);
}
file_put_contents($dir . '/MANIFEST.txt', implode("\n", $manifestLines) . "\n");
file_put_contents($dir . '/.htaccess', "Require all denied\n");
echo "       backup verified\n\n";

// ---- write -----------------------------------------------------------------------------

$written = [];

function undo($root, $before, $written)
{
    foreach ($written as $rel) {
        @file_put_contents($root . '/' . $rel, $before[$rel]);
    }
}

foreach ($plan as $rel => $new) {
    $full = $root . '/' . $rel;
    $tmp = $full . '.tmp-support-picker';

    if ($simulateFail === $rel) {
        fwrite(STDERR, "[FAIL] $rel -- simulated write failure\n");
        undo($root, $before, $written);
        echo "\nEverything written so far was put back. The site is back exactly as it was.\n";
        exit(4);
    }

    if (@file_put_contents($tmp, $new) === false || !is_file($tmp) || h16(file_get_contents($tmp)) !== h16($new)) {
        @unlink($tmp);
        fwrite(STDERR, "[FAIL] $rel -- could not write\n");
        undo($root, $before, $written);
        echo "\nEverything written so far was put back. The site is back exactly as it was.\n";
        exit(4);
    }
    if (!@rename($tmp, $full)) {
        @unlink($tmp);
        fwrite(STDERR, "[FAIL] $rel -- could not replace the file\n");
        undo($root, $before, $written);
        echo "\nEverything written so far was put back. The site is back exactly as it was.\n";
        exit(4);
    }
    if (h16(file_get_contents($full)) !== h16($new)) {
        fwrite(STDERR, "[FAIL] $rel -- the file on disk does not match what was written\n");
        undo($root, $before, $written);
        echo "\nEverything written so far was put back. The site is back exactly as it was.\n";
        exit(4);
    }

    echo "[ OK ] $rel\n";
    $written[] = $rel;
}

// ---- the changed classes must still load ------------------------------------------------

if ($classes) {
    $code = "require " . var_export($root . '/vendor/autoload.php', true) . ";\n";
    foreach ($classes as $c) {
        $code .= "if (!class_exists(" . var_export($c, true) . ")) { fwrite(STDERR, 'cannot load " . $c . "'); exit(1); }\n";
    }
    $code .= "exit(0);\n";
    $probe = $root . '/storage/deals-fix-probe.php';
    file_put_contents($probe, "<?php\n" . $code);
    $out = [];
    $rc = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' 2>&1', $out, $rc);
    @unlink($probe);

    if ($rc !== 0) {
        fwrite(STDERR, "\n[FAIL] " . implode("\n", $out) . "\n");
        undo($root, $before, $written);
        echo "\nEverything was put back. The site is back exactly as it was.\n";
        echo "If this keeps happening, run: composer dump-autoload\n";
        exit(5);
    }
    echo "\n[ OK ] every changed class loads through the autoloader\n";
}

echo "\nApplied " . count($written) . " file(s).\n";
echo "Next:\n";
echo "  php artisan view:clear\n";
echo "  php artisan optimize:clear\n";
echo "Then open /support-requests, /support-requests/create and an edit form, and search a lead.\n";
echo "To undo: php " . basename(__FILE__) . " --rollback\n";
exit(0);
