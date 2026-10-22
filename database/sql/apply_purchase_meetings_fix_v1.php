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
 *   php apply_purchase_meetings_fix_v1.php              dry run, writes nothing
 *   php apply_purchase_meetings_fix_v1.php --apply      back up, then apply everything
 *   php apply_purchase_meetings_fix_v1.php --rollback   restore the newest backup
 *
 * Exit codes: 0 fine, 2 an anchor or a preflight check failed (nothing written),
 * 3 the backup could not be taken, 4 a write failed (everything undone),
 * 5 a changed class no longer loads (everything undone).
 */

$BANNER = 'apply_purchase_meetings_fix v1  (assigned staff from the assignment pivot, sales-only Employee filter, lead search shows name and phone apart)';
$edits = array (
  0 => 
  array (
    'path' => 'app/Http/Controllers/DealPurchaseController.php',
    'label' => 'purchase eager load',
    'find' => 'ICAgICAgICAgICAgICAgIC0+d2l0aChbJ2RlYWwubGVhZC5hc3NpZ25lZFVzZXInLCAnYXR0YWNobWVudHMnXSk=',
    'replace' => 'ICAgICAgICAgICAgICAgIC0+d2l0aChbJ2RlYWwubGVhZC51c2VycycsICdhdHRhY2htZW50cyddKQ==',
  ),
  1 => 
  array (
    'path' => 'app/Http/Controllers/DealPurchaseController.php',
    'label' => 'Employee filter list',
    'find' => 'ICAgICAgICAgICAgLy8gR2V0IGVtcGxveWVlcyBmb3IgZmlsdGVyIGRyb3Bkb3duIChTYWxlcyBFbXBsb3llZSwgQ1NPLCBBbGFhLFNhYmEsYWJlciwgZmFkaSkKICAgICAgICAgICAgJGVtcGxveWVlcyA9IFxBcHBcTW9kZWxzXFVzZXI6OndoZXJlKCdjcmVhdGVkX2J5JywgJHVzci0+b3duZXJJZCgpKQogICAgICAgICAgICAgICAgLT53aGVyZShmdW5jdGlvbiAoJHEpIHsKICAgICAgICAgICAgICAgICAgICAkcS0+d2hlcmUoJ3R5cGUnLCAnQ1NPJykKICAgICAgICAgICAgICAgICAgICAgICAgLT5vcldoZXJlKCd0eXBlJywgJ0FsYWEsU2FiYSxhYmVyJykKICAgICAgICAgICAgICAgICAgICAgICAgLT5vcldoZXJlKCd0eXBlJywgJ2ZhZGknKQogICAgICAgICAgICAgICAgICAgICAgICAtPm9yV2hlcmVIYXMoJ3JvbGVzJywgZnVuY3Rpb24gKCRyb2xlUXVlcnkpIHsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICRyb2xlUXVlcnktPndoZXJlSW4oJ25hbWUnLCBbJ1NhbGVzIEVtcGxveWVlJywgJ3NhbGVzIGVtcGxveWVlJ10pOwogICAgICAgICAgICAgICAgICAgICAgICB9KTsKICAgICAgICAgICAgICAgIH0pCiAgICAgICAgICAgICAgICAtPm9yZGVyQnkoJ25hbWUnKQogICAgICAgICAgICAgICAgLT5wbHVjaygnbmFtZScsICdpZCcpOw==',
    'replace' => 'ICAgICAgICAgICAgLy8gVGhlIEVtcGxveWVlIGZpbHRlciBvZmZlcnMgc2FsZXMgcGVvcGxlIG9ubHksIHVzaW5nIHRoZSBvbmUgZGVmaW5pdGlvbgogICAgICAgICAgICAvLyBpbiBMZWFkU2NvcGUgdGhhdCB0aGUgbGVhZHMgcGFnZSBhbHNvIHVzZXMuIFRoZSBsaXN0IGhlcmUgdXNlZCB0byBiZSBhCiAgICAgICAgICAgIC8vIGhhcmQtY29kZWQgc2V0IG9mIHVzZXIgdHlwZXMgYW5kIHJvbGUgbmFtZXMsIHNvIGFueW9uZSB3aG8gc2l0cyBpbiBhCiAgICAgICAgICAgIC8vIFNhbGVzIGRlcGFydG1lbnQgd2l0aG91dCBvbmUgb2YgdGhvc2UgZXhhY3QgbGFiZWxzIHdhcyBtaXNzaW5nIGZyb20gaXQuCiAgICAgICAgICAgICRlbXBsb3llZXMgPSBcQXBwXFNlcnZpY2VzXExlYWRTY29wZTo6c2FsZXNVc2Vyc1F1ZXJ5KCR1c3IpCiAgICAgICAgICAgICAgICAtPm9yZGVyQnkoJ3VzZXJzLm5hbWUnKQogICAgICAgICAgICAgICAgLT5nZXQoKQogICAgICAgICAgICAgICAgLT5wbHVjaygnbmFtZScsICdpZCcpOw==',
  ),
  2 => 
  array (
    'path' => 'app/Http/Controllers/SalesMeetingController.php',
    'label' => 'meeting eager load',
    'find' => 'ICAgICAgICAgICAgLy8gbGVhZC5hc3NpZ25lZFVzZXIgaXMgcmVhZCBmb3IgZXZlcnkgcm93IGJ5IHRoZSBBc3NpZ25lZCBTdGFmZiBjb2x1bW4uCiAgICAgICAgICAgICRtZWV0aW5ncyA9ICRxdWVyeS0+d2l0aChbJ2xlYWQuYXNzaWduZWRVc2VyJywgJ2RlYWwnLCAnbWVldGluZ1R5cGUnXSk=',
    'replace' => 'ICAgICAgICAgICAgLy8gbGVhZC51c2VycyBpcyB0aGUgYXNzaWdubWVudCBwaXZvdCB0aGUgQXNzaWduZWQgU3RhZmYgY29sdW1uIHJlYWRzLgogICAgICAgICAgICAkbWVldGluZ3MgPSAkcXVlcnktPndpdGgoWydsZWFkLnVzZXJzJywgJ2RlYWwnLCAnbWVldGluZ1R5cGUnXSk=',
  ),
  3 => 
  array (
    'path' => 'app/Http/Controllers/SalesMeetingController.php',
    'label' => 'lead search payload',
    'find' => 'ICAgICAgICByZXR1cm4gcmVzcG9uc2UoKS0+anNvbigkbGVhZHMtPm1hcChmdW5jdGlvbiAoJGxlYWQpIHsKICAgICAgICAgICAgcmV0dXJuIFsnaWQnID0+ICRsZWFkLT5pZCwgJ3RleHQnID0+ICRsZWFkLT5uYW1lIC4gKCRsZWFkLT5waG9uZSA/ICcgLSAnIC4gJGxlYWQtPnBob25lIDogJycpXTsKICAgICAgICB9KS0+dmFsdWVzKCkpOw==',
    'replace' => 'ICAgICAgICAvLyBuYW1lIGFuZCBwaG9uZSBhcmUgc2VudCBhcGFydCBzbyB0aGUgcGlja2VyIGNhbiBwdXQgdGhlbSBvbiB0aGVpciBvd24KICAgICAgICAvLyBsaW5lcy4gSm9pbmVkIGludG8gb25lICJuYW1lIC0gcGhvbmUiIHN0cmluZyB0aGV5IHJlbmRlcmVkIGluIHRoZSB3cm9uZwogICAgICAgIC8vIG9yZGVyIGZvciBBcmFiaWMgbmFtZXMsIGJlY2F1c2UgdGhlIGJpZGlyZWN0aW9uYWwgYWxnb3JpdGhtIG1vdmVkIHRoZQogICAgICAgIC8vIGRpZ2l0cyB0byB0aGUgb3RoZXIgZW5kIG9mIHRoZSBsaW5lLgogICAgICAgIHJldHVybiByZXNwb25zZSgpLT5qc29uKCRsZWFkcy0+bWFwKGZ1bmN0aW9uICgkbGVhZCkgewogICAgICAgICAgICByZXR1cm4gWwogICAgICAgICAgICAgICAgJ2lkJyA9PiAkbGVhZC0+aWQsCiAgICAgICAgICAgICAgICAnbmFtZScgPT4gJGxlYWQtPm5hbWUsCiAgICAgICAgICAgICAgICAncGhvbmUnID0+IChzdHJpbmcpICRsZWFkLT5waG9uZSwKICAgICAgICAgICAgICAgIC8vIGtlcHQgc28gYSBicm93c2VyIGhvbGRpbmcgdGhlIHByZXZpb3VzIHNjcmlwdCBzdGlsbCBzaG93cyBzb21ldGhpbmcKICAgICAgICAgICAgICAgICd0ZXh0JyA9PiAkbGVhZC0+bmFtZSAuICgkbGVhZC0+cGhvbmUgPyAnIC0gJyAuICRsZWFkLT5waG9uZSA6ICcnKSwKICAgICAgICAgICAgXTsKICAgICAgICB9KS0+dmFsdWVzKCkpOw==',
  ),
  4 => 
  array (
    'path' => 'resources/views/admin/deal_purchase/_table.blade.php',
    'label' => 'purchase Assigned Staff',
    'find' => 'ICAgICAgICAgICAgICAgICAgICA8dGQ+CiAgICAgICAgICAgICAgICAgICAgICAgIEBpZiAoIWVtcHR5KCRwdXJjaGFzZS0+ZGVhbCkgJiYgIWVtcHR5KCRwdXJjaGFzZS0+ZGVhbC0+bGVhZCkgJiYgIWVtcHR5KCRwdXJjaGFzZS0+ZGVhbC0+bGVhZC0+YXNzaWduZWRVc2VyKSkKICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxhIGhyZWY9IiMiIGNsYXNzPSJidG4gYnRuLXNtIG1yLTEgcC0wIHJvdW5kZWQtY2lyY2xlIj4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8aW1nIGFsdD0iaW1hZ2UiIGRhdGEtYnMtdG9nZ2xlPSJ0b29sdGlwIgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB0aXRsZT0ie3sgJHB1cmNoYXNlLT5kZWFsLT5sZWFkLT5hc3NpZ25lZFVzZXItPm5hbWUgfX0iCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIEBpZiAoJHB1cmNoYXNlLT5kZWFsLT5sZWFkLT5hc3NpZ25lZFVzZXItPmF2YXRhcikgc3JjPSJ7eyBhc3NldCgnL3N0b3JhZ2UvdXBsb2Fkcy9hdmF0YXIvJyAuICRwdXJjaGFzZS0+ZGVhbC0+bGVhZC0+YXNzaWduZWRVc2VyLT5hdmF0YXIpIH19IiBAZWxzZSBzcmM9Int7IGFzc2V0KCcvc3RvcmFnZS91cGxvYWRzL2F2YXRhci9hdmF0YXIucG5nJykgfX0iIEBlbmRpZgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBjbGFzcz0icm91bmRlZC1jaXJjbGUgIiB3aWR0aD0iMjUiIGhlaWdodD0iMjUiPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgPC9hPgogICAgICAgICAgICAgICAgICAgICAgICBAZWxzZQogICAgICAgICAgICAgICAgICAgICAgICAgICAgPHNwYW4gY2xhc3M9InRleHQtbXV0ZWQiPi08L3NwYW4+CiAgICAgICAgICAgICAgICAgICAgICAgIEBlbmRpZgogICAgICAgICAgICAgICAgICAgIDwvdGQ+',
    'replace' => 'ICAgICAgICAgICAgICAgICAgICA8dGQ+CiAgICAgICAgICAgICAgICAgICAgICAgIHt7LS0gUmVhZCBmcm9tIHRoZSB1c2VyX2xlYWRzIHBpdm90LCB0aGUgc2FtZSBzb3VyY2UgdGhlIGxlYWQgcGFnZSBhbmQKICAgICAgICAgICAgICAgICAgICAgICAgICAgICB0aGUgbGVhZHMgbGlzdCB1c2UuIFRoaXMgY29sdW1uIHVzZWQgdG8gcmVhZCBsZWFkcy51c2VyX2lkLCBhCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgc2VwYXJhdGUgY29sdW1uIHRoYXQgY2FuIHBvaW50IGF0IGEgZGlmZmVyZW50IHBlcnNvbiwgc28gdGhlIHR3bwogICAgICAgICAgICAgICAgICAgICAgICAgICAgIHNjcmVlbnMgbmFtZWQgZGlmZmVyZW50IHN0YWZmIGZvciB0aGUgc2FtZSBsZWFkLiAtLX19CiAgICAgICAgICAgICAgICAgICAgICAgIEBmb3JlbHNlICghZW1wdHkoJHB1cmNoYXNlLT5kZWFsKSAmJiAhZW1wdHkoJHB1cmNoYXNlLT5kZWFsLT5sZWFkKSA/ICRwdXJjaGFzZS0+ZGVhbC0+bGVhZC0+dXNlcnMgOiBbXSBhcyAkdXNlcikKICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxzcGFuIGNsYXNzPSJkLWlubGluZS1mbGV4IGFsaWduLWl0ZW1zLWNlbnRlciBtZS0yIj4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8aW1nIGFsdD0ie3sgJHVzZXItPm5hbWUgfX0iCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIEBpZiAoJHVzZXItPmF2YXRhcikgc3JjPSJ7eyBhc3NldCgnL3N0b3JhZ2UvdXBsb2Fkcy9hdmF0YXIvJyAuICR1c2VyLT5hdmF0YXIpIH19IiBAZWxzZSBzcmM9Int7IGFzc2V0KCcvc3RvcmFnZS91cGxvYWRzL2F2YXRhci9hdmF0YXIucG5nJykgfX0iIEBlbmRpZgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBjbGFzcz0icm91bmRlZC1jaXJjbGUgbWUtMSIgd2lkdGg9IjI1IiBoZWlnaHQ9IjI1Ij4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8c3BhbiBkaXI9ImF1dG8iPnt7ICR1c2VyLT5uYW1lIH19PC9zcGFuPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgPC9zcGFuPgogICAgICAgICAgICAgICAgICAgICAgICBAZW1wdHkKICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxzcGFuIGNsYXNzPSJ0ZXh0LW11dGVkIj4tPC9zcGFuPgogICAgICAgICAgICAgICAgICAgICAgICBAZW5kZm9yZWxzZQogICAgICAgICAgICAgICAgICAgIDwvdGQ+',
  ),
  5 => 
  array (
    'path' => 'resources/views/sales_meetings/_table.blade.php',
    'label' => 'meeting Assigned Staff',
    'find' => 'ICAgICAgICAgICAgICAgICAgICA8dGQ+CiAgICAgICAgICAgICAgICAgICAgICAgIEBpZiAoJG1lZXRpbmctPmxlYWQgJiYgJG1lZXRpbmctPmxlYWQtPmFzc2lnbmVkVXNlcikKICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxhIGhyZWY9IiMiIGNsYXNzPSJidG4gYnRuLXNtIG1yLTEgcC0wIHJvdW5kZWQtY2lyY2xlIj4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8aW1nIGFsdD0iaW1hZ2UiIGRhdGEtYnMtdG9nZ2xlPSJ0b29sdGlwIgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB0aXRsZT0ie3sgJG1lZXRpbmctPmxlYWQtPmFzc2lnbmVkVXNlci0+bmFtZSB9fSIKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgQGlmICgkbWVldGluZy0+bGVhZC0+YXNzaWduZWRVc2VyLT5hdmF0YXIpIHNyYz0ie3sgYXNzZXQoJy9zdG9yYWdlL3VwbG9hZHMvYXZhdGFyLycgLiAkbWVldGluZy0+bGVhZC0+YXNzaWduZWRVc2VyLT5hdmF0YXIpIH19IiBAZWxzZSBzcmM9Int7IGFzc2V0KCcvc3RvcmFnZS91cGxvYWRzL2F2YXRhci9hdmF0YXIucG5nJykgfX0iIEBlbmRpZgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBjbGFzcz0icm91bmRlZC1jaXJjbGUgIiB3aWR0aD0iMjUiIGhlaWdodD0iMjUiPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgPC9hPgogICAgICAgICAgICAgICAgICAgICAgICBAZWxzZQogICAgICAgICAgICAgICAgICAgICAgICAgICAgLQogICAgICAgICAgICAgICAgICAgICAgICBAZW5kaWYKICAgICAgICAgICAgICAgICAgICA8L3RkPg==',
    'replace' => 'ICAgICAgICAgICAgICAgICAgICA8dGQ+CiAgICAgICAgICAgICAgICAgICAgICAgIHt7LS0gUmVhZCBmcm9tIHRoZSB1c2VyX2xlYWRzIHBpdm90LCB0aGUgc2FtZSBzb3VyY2UgdGhlIGxlYWQgcGFnZSBhbmQKICAgICAgICAgICAgICAgICAgICAgICAgICAgICB0aGUgbGVhZHMgbGlzdCB1c2UuIFRoaXMgY29sdW1uIHVzZWQgdG8gcmVhZCBsZWFkcy51c2VyX2lkLCBhCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgc2VwYXJhdGUgY29sdW1uIHRoYXQgY2FuIHBvaW50IGF0IGEgZGlmZmVyZW50IHBlcnNvbiwgc28gdGhlIHR3bwogICAgICAgICAgICAgICAgICAgICAgICAgICAgIHNjcmVlbnMgbmFtZWQgZGlmZmVyZW50IHN0YWZmIGZvciB0aGUgc2FtZSBsZWFkLiAtLX19CiAgICAgICAgICAgICAgICAgICAgICAgIEBmb3JlbHNlICgkbWVldGluZy0+bGVhZCA/ICRtZWV0aW5nLT5sZWFkLT51c2VycyA6IFtdIGFzICR1c2VyKQogICAgICAgICAgICAgICAgICAgICAgICAgICAgPHNwYW4gY2xhc3M9ImQtaW5saW5lLWZsZXggYWxpZ24taXRlbXMtY2VudGVyIG1lLTIiPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxpbWcgYWx0PSJ7eyAkdXNlci0+bmFtZSB9fSIKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgQGlmICgkdXNlci0+YXZhdGFyKSBzcmM9Int7IGFzc2V0KCcvc3RvcmFnZS91cGxvYWRzL2F2YXRhci8nIC4gJHVzZXItPmF2YXRhcikgfX0iIEBlbHNlIHNyYz0ie3sgYXNzZXQoJy9zdG9yYWdlL3VwbG9hZHMvYXZhdGFyL2F2YXRhci5wbmcnKSB9fSIgQGVuZGlmCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGNsYXNzPSJyb3VuZGVkLWNpcmNsZSBtZS0xIiB3aWR0aD0iMjUiIGhlaWdodD0iMjUiPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxzcGFuIGRpcj0iYXV0byI+e3sgJHVzZXItPm5hbWUgfX08L3NwYW4+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICA8L3NwYW4+CiAgICAgICAgICAgICAgICAgICAgICAgIEBlbXB0eQogICAgICAgICAgICAgICAgICAgICAgICAgICAgPHNwYW4gY2xhc3M9InRleHQtbXV0ZWQiPi08L3NwYW4+CiAgICAgICAgICAgICAgICAgICAgICAgIEBlbmRmb3JlbHNlCiAgICAgICAgICAgICAgICAgICAgPC90ZD4=',
  ),
  6 => 
  array (
    'path' => 'resources/views/sales_meetings/_search_picker.blade.php',
    'label' => 'result rows',
    'find' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICQuZWFjaChyZXMsIGZ1bmN0aW9uKGksIGl0ZW0pIHsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAkbGlzdC5hcHBlbmQoJCgnPGEgaHJlZj0iIyIgY2xhc3M9Imxpc3QtZ3JvdXAtaXRlbSBsaXN0LWdyb3VwLWl0ZW0tYWN0aW9uIHNtLXBpY2tlci1vcHRpb24iPjwvYT4nKQogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAuYXR0cignZGF0YS1pZCcsIGl0ZW0uaWQpLnRleHQoaXRlbS50ZXh0KSk7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICB9KTs=',
    'replace' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICQuZWFjaChyZXMsIGZ1bmN0aW9uKGksIGl0ZW0pIHsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB2YXIgJG9wdCA9ICQoJzxhIGhyZWY9IiMiIGNsYXNzPSJsaXN0LWdyb3VwLWl0ZW0gbGlzdC1ncm91cC1pdGVtLWFjdGlvbiBzbS1waWNrZXItb3B0aW9uIj48L2E+JykKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgLmF0dHIoJ2RhdGEtaWQnLCBpdGVtLmlkKTsKCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgLy8gTmFtZSBhbmQgcGhvbmUgZ28gb24gc2VwYXJhdGUgbGluZXMsIGVhY2ggd2l0aCBpdHMKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAvLyBvd24gZGlyZWN0aW9uLiBPbiBvbmUgbGluZSBhbiBBcmFiaWMgbmFtZSBhbmQgYQogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIC8vIExhdGluLWRpZ2l0IHBob25lIHN3YXAgcGxhY2VzIGFuZCByZWFkIGFzIGlmIHRoZQogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIC8vIG51bWJlciBjYW1lIGZpcnN0LiBFbmRwb2ludHMgdGhhdCBzZW5kIG5vIG5hbWUKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAvLyAodGhlIGRlYWwgc2VhcmNoKSBzdGlsbCB3b3JrIHRocm91Z2ggaXRlbS50ZXh0LgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHZhciBsYWJlbCA9IGl0ZW0ubmFtZSAhPSBudWxsID8gaXRlbS5uYW1lIDogaXRlbS50ZXh0OwogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICRvcHQuYXR0cignZGF0YS1sYWJlbCcsIGxhYmVsKTsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAkb3B0LmFwcGVuZCgkKCc8c3BhbiBjbGFzcz0iZC1ibG9jayIgZGlyPSJhdXRvIj48L3NwYW4+JykudGV4dChsYWJlbCkpOwogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGlmIChpdGVtLnBob25lKSB7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICRvcHQuYXBwZW5kKCQoCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJzxzcGFuIGNsYXNzPSJkLWJsb2NrIHNtYWxsIHRleHQtbXV0ZWQiIGRpcj0ibHRyIiBzdHlsZT0idGV4dC1hbGlnbjpzdGFydCI+PC9zcGFuPicKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICkKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIC50ZXh0KGl0ZW0ucGhvbmUpKTsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB9CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJGxpc3QuYXBwZW5kKCRvcHQpOwogICAgICAgICAgICAgICAgICAgICAgICAgICAgfSk7',
  ),
  7 => 
  array (
    'path' => 'resources/views/sales_meetings/_search_picker.blade.php',
    'label' => 'picked label',
    'find' => 'ICAgICAgICAgICAgICAgIHZhciAkd3JhcCA9ICQodGhpcykuY2xvc2VzdCgnLnNtLXBpY2tlcicpOwogICAgICAgICAgICAgICAgJHdyYXAuZmluZCgnLnNtLXBpY2tlci1zZWFyY2gnKS52YWwoJCh0aGlzKS50ZXh0KCkpLnJlbW92ZUNsYXNzKCdpcy1pbnZhbGlkJyk7CiAgICAgICAgICAgICAgICBoaWRkZW4oJHdyYXApLnZhbCgkKHRoaXMpLmRhdGEoJ2lkJykpLnRyaWdnZXIoJ2NoYW5nZScpOw==',
    'replace' => 'ICAgICAgICAgICAgICAgIHZhciAkd3JhcCA9ICQodGhpcykuY2xvc2VzdCgnLnNtLXBpY2tlcicpOwogICAgICAgICAgICAgICAgLy8gZGF0YS1sYWJlbCwgbm90IC50ZXh0KCk6IHRoZSBvcHRpb24gbm93IGhvbGRzIHR3byBlbGVtZW50cywgYW5kCiAgICAgICAgICAgICAgICAvLyB0aGVpciBjb25jYXRlbmF0ZWQgdGV4dCB3b3VsZCBnbHVlIHRoZSBwaG9uZSBvbnRvIHRoZSBuYW1lLgogICAgICAgICAgICAgICAgdmFyIGxhYmVsID0gJCh0aGlzKS5hdHRyKCdkYXRhLWxhYmVsJyk7CiAgICAgICAgICAgICAgICAkd3JhcC5maW5kKCcuc20tcGlja2VyLXNlYXJjaCcpLnZhbChsYWJlbCAhPSBudWxsID8gbGFiZWwgOiAkKHRoaXMpLnRleHQoKSkucmVtb3ZlQ2xhc3MoJ2lzLWludmFsaWQnKTsKICAgICAgICAgICAgICAgIGhpZGRlbigkd3JhcCkudmFsKCQodGhpcykuZGF0YSgnaWQnKSkudHJpZ2dlcignY2hhbmdlJyk7',
  ),
);
$classes = array (
  0 => 'App\\Http\\Controllers\\DealPurchaseController',
  1 => 'App\\Http\\Controllers\\SalesMeetingController',
  2 => 'App\\Services\\LeadScope',
);
$backupPrefix = 'purchase-meetings-fix';

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
    $tmp = $full . '.tmp-purchase-meetings-fix';

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
echo "Then open /deal-purchase and /sales-meetings, check the Assigned Staff column,\n";
echo "the Employee filter, and a lead search by name and by phone.\n";
echo "To undo: php " . basename(__FILE__) . " --rollback\n";
exit(0);
