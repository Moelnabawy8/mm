<?php
/**
 * Anchored in-place patch.
 *
 * Shared files are never shipped whole: the server copy can be ahead of the
 * local checkout, so replacing them would throw away whatever is only on the
 * server. Two NEW files must be uploaded first (see $requiredNewFiles); the
 * script refuses to run until they are in place and match. Each change is an exact find ->
 * replace pair instead, and the script refuses to touch anything unless every
 * anchor is found exactly once.
 *
 *   php apply_api_routes_fix_v1.php              dry run, writes nothing
 *   php apply_api_routes_fix_v1.php --apply      back up, then apply everything
 *   php apply_api_routes_fix_v1.php --rollback   restore the newest backup
 *
 * Exit codes: 0 fine, 2 an anchor or a preflight check failed (nothing written),
 * 3 the backup could not be taken, 4 a write failed (everything undone),
 * 5 a changed class no longer loads (everything undone).
 */

$BANNER = 'apply_api_routes_fix v1  (20 broken /api/v1 routes, 4 shadowed routes, legacy project routes tenant guard)';
$edits = array (
  0 => 
  array (
    'path' => 'routes/api.php',
    'label' => 'use statements',
    'find' => 'dXNlIEFwcFxIdHRwXENvbnRyb2xsZXJzXEFwaVxFbXBsb3llZVNhbGFyeUFwaUNvbnRyb2xsZXI7',
    'replace' => 'dXNlIEFwcFxIdHRwXENvbnRyb2xsZXJzXEFwaVxFbXBsb3llZVNhbGFyeUFwaUNvbnRyb2xsZXI7CnVzZSBBcHBcSHR0cFxDb250cm9sbGVyc1xBcGlcU3VwcG9ydFJlcXVlc3RBcGlDb250cm9sbGVyOwp1c2UgQXBwXEh0dHBcQ29udHJvbGxlcnNcQXBpXERvY3VtZW50QXBpQ29udHJvbGxlcjs=',
  ),
  1 => 
  array (
    'path' => 'routes/api.php',
    'label' => 'attendance import/export above {id}',
    'find' => 'ICAgICAgICAgICAgUm91dGU6OnBvc3QoJ2J1bGsnLCBbQXR0ZW5kYW5jZUFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2J1bGtBdHRlbmRhbmNlJ10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCcve2lkfScsIFtBdHRlbmRhbmNlQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnZ2V0QXR0ZW5kYW5jZURldGFpbCddKTsKICAgICAgICAgICAgUm91dGU6OnB1dCgnL3tpZH0nLCBbQXR0ZW5kYW5jZUFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ3VwZGF0ZUF0dGVuZGFuY2UnXSk7CiAgICAgICAgICAgIFJvdXRlOjpkZWxldGUoJy97aWR9JywgW0F0dGVuZGFuY2VBcGlDb250cm9sbGVyOjpjbGFzcyAsICdkZWxldGVBdHRlbmRhbmNlJ10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCdlbXBsb3llZS97aWR9JywgW0F0dGVuZGFuY2VBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXRFbXBsb3llZUF0dGVuZGFuY2VMaXN0J10pOwogICAgICAgICAgICBSb3V0ZTo6cG9zdCgnaW1wb3J0JywgW0F0dGVuZGFuY2VBcGlDb250cm9sbGVyOjpjbGFzcyAsICdpbXBvcnRBdHRlbmRhbmNlJ10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCdleHBvcnQnLCBbQXR0ZW5kYW5jZUFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2V4cG9ydEF0dGVuZGFuY2UnXSk7',
    'replace' => 'ICAgICAgICAgICAgUm91dGU6OnBvc3QoJ2J1bGsnLCBbQXR0ZW5kYW5jZUFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2J1bGtBdHRlbmRhbmNlJ10pOwogICAgICAgICAgICBSb3V0ZTo6cG9zdCgnaW1wb3J0JywgW0F0dGVuZGFuY2VBcGlDb250cm9sbGVyOjpjbGFzcyAsICdpbXBvcnRBdHRlbmRhbmNlJ10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCdleHBvcnQnLCBbQXR0ZW5kYW5jZUFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2V4cG9ydEF0dGVuZGFuY2UnXSk7CiAgICAgICAgICAgIFJvdXRlOjpnZXQoJy97aWR9JywgW0F0dGVuZGFuY2VBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXRBdHRlbmRhbmNlRGV0YWlsJ10pOwogICAgICAgICAgICBSb3V0ZTo6cHV0KCcve2lkfScsIFtBdHRlbmRhbmNlQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAndXBkYXRlQXR0ZW5kYW5jZSddKTsKICAgICAgICAgICAgUm91dGU6OmRlbGV0ZSgnL3tpZH0nLCBbQXR0ZW5kYW5jZUFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2RlbGV0ZUF0dGVuZGFuY2UnXSk7CiAgICAgICAgICAgIFJvdXRlOjpnZXQoJ2VtcGxveWVlL3tpZH0nLCBbQXR0ZW5kYW5jZUFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2dldEVtcGxveWVlQXR0ZW5kYW5jZUxpc3QnXSk7',
  ),
  2 => 
  array (
    'path' => 'routes/api.php',
    'label' => 'trainings types/trainers above {id}',
    'find' => 'ICAgICAgICAgICAgUm91dGU6OnBvc3QoJy8nLCBbVHJhaW5pbmdBcGlDb250cm9sbGVyOjpjbGFzcyAsICdjcmVhdGVUcmFpbmluZyddKTsKICAgICAgICAgICAgUm91dGU6OmdldCgnL3tpZH0nLCBbVHJhaW5pbmdBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXRUcmFpbmluZyddKTsKICAgICAgICAgICAgUm91dGU6OnB1dCgnL3tpZH0nLCBbVHJhaW5pbmdBcGlDb250cm9sbGVyOjpjbGFzcyAsICd1cGRhdGVUcmFpbmluZyddKTsKICAgICAgICAgICAgUm91dGU6OmRlbGV0ZSgnL3tpZH0nLCBbVHJhaW5pbmdBcGlDb250cm9sbGVyOjpjbGFzcyAsICdkZWxldGVUcmFpbmluZyddKTsKICAgICAgICAgICAgUm91dGU6OmdldCgndHlwZXMnLCBbVHJhaW5pbmdBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXRUcmFpbmluZ1R5cGVzJ10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCd0cmFpbmVycycsIFtUcmFpbmluZ0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2dldFRyYWluZXJzJ10pOw==',
    'replace' => 'ICAgICAgICAgICAgUm91dGU6OnBvc3QoJy8nLCBbVHJhaW5pbmdBcGlDb250cm9sbGVyOjpjbGFzcyAsICdjcmVhdGVUcmFpbmluZyddKTsKICAgICAgICAgICAgUm91dGU6OmdldCgndHlwZXMnLCBbVHJhaW5pbmdBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXRUcmFpbmluZ1R5cGVzJ10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCd0cmFpbmVycycsIFtUcmFpbmluZ0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2dldFRyYWluZXJzJ10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCcve2lkfScsIFtUcmFpbmluZ0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2dldFRyYWluaW5nJ10pOwogICAgICAgICAgICBSb3V0ZTo6cHV0KCcve2lkfScsIFtUcmFpbmluZ0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ3VwZGF0ZVRyYWluaW5nJ10pOwogICAgICAgICAgICBSb3V0ZTo6ZGVsZXRlKCcve2lkfScsIFtUcmFpbmluZ0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2RlbGV0ZVRyYWluaW5nJ10pOw==',
  ),
  3 => 
  array (
    'path' => 'routes/api.php',
    'label' => 'jobs categories above {id}',
    'find' => 'ICAgICAgICAgICAgUm91dGU6OnBvc3QoJy8nLCBbSm9iQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnY3JlYXRlSm9iJ10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCcve2lkfScsIFtKb2JBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXRKb2InXSk7CiAgICAgICAgICAgIFJvdXRlOjpwdXQoJy97aWR9JywgW0pvYkFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ3VwZGF0ZUpvYiddKTsKICAgICAgICAgICAgUm91dGU6OmRlbGV0ZSgnL3tpZH0nLCBbSm9iQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnZGVsZXRlSm9iJ10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCcve2lkfS9hcHBsaWNhdGlvbnMnLCBbSm9iQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnZ2V0Sm9iQXBwbGljYXRpb25zJ10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCdjYXRlZ29yaWVzJywgW0pvYkFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2dldEpvYkNhdGVnb3JpZXMnXSk7',
    'replace' => 'ICAgICAgICAgICAgUm91dGU6OnBvc3QoJy8nLCBbSm9iQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnY3JlYXRlSm9iJ10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCdjYXRlZ29yaWVzJywgW0pvYkFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2dldEpvYkNhdGVnb3JpZXMnXSk7CiAgICAgICAgICAgIFJvdXRlOjpnZXQoJy97aWR9JywgW0pvYkFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2dldEpvYiddKTsKICAgICAgICAgICAgUm91dGU6OnB1dCgnL3tpZH0nLCBbSm9iQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAndXBkYXRlSm9iJ10pOwogICAgICAgICAgICBSb3V0ZTo6ZGVsZXRlKCcve2lkfScsIFtKb2JBcGlDb250cm9sbGVyOjpjbGFzcyAsICdkZWxldGVKb2InXSk7CiAgICAgICAgICAgIFJvdXRlOjpnZXQoJy97aWR9L2FwcGxpY2F0aW9ucycsIFtKb2JBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXRKb2JBcHBsaWNhdGlvbnMnXSk7',
  ),
  4 => 
  array (
    'path' => 'routes/api.php',
    'label' => 'task check-in/out',
    'find' => 'ICAgICAgICAgICAgUm91dGU6OnBvc3QoJy97aWR9L2NoZWNrLWluJywgW1Rhc2tBcGlDb250cm9sbGVyOjpjbGFzcyAsICdjaGVja0luVGFzayddKTsKICAgICAgICAgICAgUm91dGU6OnBvc3QoJy97aWR9L2NoZWNrLW91dCcsIFtUYXNrQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnY2hlY2tPdXRUYXNrJ10pOw==',
    'replace' => 'ICAgICAgICAgICAgLy8gY2hlY2staW4gLyBjaGVjay1vdXQgYXJlIGFsaWFzZXMgb2YgdGhlIHRhc2sgdGltZSB0cmFja2VyIHN0YXJ0IC8gc3RvcAogICAgICAgICAgICBSb3V0ZTo6cG9zdCgnL3tpZH0vY2hlY2staW4nLCBbVGFza0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ3N0YXJ0VGltZVRyYWNrZXInXSk7CiAgICAgICAgICAgIFJvdXRlOjpwb3N0KCcve2lkfS9jaGVjay1vdXQnLCBbVGFza0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ3N0b3BUaW1lVHJhY2tlciddKTs=',
  ),
  5 => 
  array (
    'path' => 'routes/api.php',
    'label' => 'support requests',
    'find' => 'ICAgICAgICAgICAgUm91dGU6OmdldCgnLycsIFtBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXRTdXBwb3J0UmVxdWVzdHMnXSk7CiAgICAgICAgICAgIFJvdXRlOjpwb3N0KCcvJywgW0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2NyZWF0ZVN1cHBvcnRSZXF1ZXN0J10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCcve2lkfScsIFtBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXRTdXBwb3J0UmVxdWVzdCddKTsKICAgICAgICAgICAgUm91dGU6OnB1dCgnL3tpZH0nLCBbQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAndXBkYXRlU3VwcG9ydFJlcXVlc3QnXSk7CiAgICAgICAgICAgIFJvdXRlOjpwb3N0KCcve2lkfS91cGRhdGUtc3RhdHVzJywgW0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ3VwZGF0ZVN1cHBvcnRSZXF1ZXN0U3RhdHVzJ10pOw==',
    'replace' => 'ICAgICAgICAgICAgUm91dGU6OmdldCgnLycsIFtTdXBwb3J0UmVxdWVzdEFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2dldFN1cHBvcnRSZXF1ZXN0cyddKTsKICAgICAgICAgICAgUm91dGU6OnBvc3QoJy8nLCBbU3VwcG9ydFJlcXVlc3RBcGlDb250cm9sbGVyOjpjbGFzcyAsICdjcmVhdGVTdXBwb3J0UmVxdWVzdCddKTsKICAgICAgICAgICAgUm91dGU6OmdldCgnL3tpZH0nLCBbU3VwcG9ydFJlcXVlc3RBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXRTdXBwb3J0UmVxdWVzdCddKTsKICAgICAgICAgICAgUm91dGU6OnB1dCgnL3tpZH0nLCBbU3VwcG9ydFJlcXVlc3RBcGlDb250cm9sbGVyOjpjbGFzcyAsICd1cGRhdGVTdXBwb3J0UmVxdWVzdCddKTsKICAgICAgICAgICAgUm91dGU6OnBvc3QoJy97aWR9L3VwZGF0ZS1zdGF0dXMnLCBbU3VwcG9ydFJlcXVlc3RBcGlDb250cm9sbGVyOjpjbGFzcyAsICd1cGRhdGVTdXBwb3J0UmVxdWVzdFN0YXR1cyddKTs=',
  ),
  6 => 
  array (
    'path' => 'routes/api.php',
    'label' => 'documents + document-uploads',
    'find' => 'ICAgICAgICBSb3V0ZTo6cHJlZml4KCdkb2N1bWVudHMnKS0+Z3JvdXAoZnVuY3Rpb24gKCkgewogICAgICAgICAgICBSb3V0ZTo6Z2V0KCcvJywgW0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2dldERvY3VtZW50cyddKTsKICAgICAgICAgICAgUm91dGU6OnBvc3QoJy8nLCBbQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAndXBsb2FkRG9jdW1lbnQnXSk7CiAgICAgICAgICAgIFJvdXRlOjpnZXQoJy97aWR9JywgW0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2dldERvY3VtZW50J10pOwogICAgICAgICAgICBSb3V0ZTo6ZGVsZXRlKCcve2lkfScsIFtBcGlDb250cm9sbGVyOjpjbGFzcyAsICdkZWxldGVEb2N1bWVudCddKTsKICAgICAgICAgICAgUm91dGU6OmdldCgnL3tpZH0vZG93bmxvYWQnLCBbQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnZG93bmxvYWREb2N1bWVudCddKTsKICAgICAgICB9CiAgICAgICAgKTs=',
    'replace' => 'ICAgICAgICAvLyAvZG9jdW1lbnRzID0gZG9jdW1lbnQgdHlwZXM7IC9kb2N1bWVudC11cGxvYWRzID0gdGhlIHVwbG9hZGVkIGZpbGVzIChzZXBhcmF0ZSBpZCBzcGFjZXMpCiAgICAgICAgUm91dGU6OnByZWZpeCgnZG9jdW1lbnRzJyktPmdyb3VwKGZ1bmN0aW9uICgpIHsKICAgICAgICAgICAgUm91dGU6OmdldCgnLycsIFtBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXREb2N1bWVudHMnXSk7CiAgICAgICAgICAgIFJvdXRlOjpwb3N0KCcvJywgW0RvY3VtZW50QXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnY3JlYXRlRG9jdW1lbnRUeXBlJ10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCcve2lkfScsIFtBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXREb2N1bWVudCddKTsKICAgICAgICAgICAgUm91dGU6OmRlbGV0ZSgnL3tpZH0nLCBbRG9jdW1lbnRBcGlDb250cm9sbGVyOjpjbGFzcyAsICdkZWxldGVEb2N1bWVudFR5cGUnXSk7CiAgICAgICAgfQogICAgICAgICk7CgogICAgICAgIFJvdXRlOjpwcmVmaXgoJ2RvY3VtZW50LXVwbG9hZHMnKS0+Z3JvdXAoZnVuY3Rpb24gKCkgewogICAgICAgICAgICBSb3V0ZTo6Z2V0KCcvJywgW0RvY3VtZW50QXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnZ2V0RG9jdW1lbnRVcGxvYWRzJ10pOwogICAgICAgICAgICBSb3V0ZTo6cG9zdCgnLycsIFtEb2N1bWVudEFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ3VwbG9hZERvY3VtZW50J10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCcve2lkfScsIFtEb2N1bWVudEFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2dldERvY3VtZW50VXBsb2FkJ10pOwogICAgICAgICAgICBSb3V0ZTo6ZGVsZXRlKCcve2lkfScsIFtEb2N1bWVudEFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2RlbGV0ZURvY3VtZW50J10pOwogICAgICAgICAgICBSb3V0ZTo6Z2V0KCcve2lkfS9kb3dubG9hZCcsIFtEb2N1bWVudEFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2Rvd25sb2FkRG9jdW1lbnQnXSk7CiAgICAgICAgfSk7',
  ),
  7 => 
  array (
    'path' => 'routes/api.php',
    'label' => 'legacy project routes',
    'find' => 'ICAgICAgICBSb3V0ZTo6cG9zdCgndXBkYXRlLXByb2plY3QnLCBbUHJvamVjdEFwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ3VwZGF0ZVByb2plY3QnXSk7IC8vIFJlZHVuZGFudAogICAgICAgIFJvdXRlOjpwb3N0KCdkZWxldGUtcHJvamVjdCcsIFtQcm9qZWN0QXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnZGVsZXRlUHJvamVjdCddKTsgLy8gUmVkdW5kYW50CiAgICAgICAgUm91dGU6OnBvc3QoJ2R1cGxpY2F0ZS1wcm9qZWN0JywgW1Byb2plY3RBcGlDb250cm9sbGVyOjpjbGFzcyAsICdkdXBsaWNhdGVQcm9qZWN0J10pOyAvLyBSZWR1bmRhbnQKICAgICAgICBSb3V0ZTo6Z2V0KCdnZXQtcHJvamVjdC10YXNrcycsIFtQcm9qZWN0QXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnZ2V0UHJvamVjdFRhc2tzJ10pOyAvLyBSZWR1bmRhbnQKICAgICAgICBSb3V0ZTo6cG9zdCgnY3JlYXRlLXByb2plY3QtdGFzaycsIFtUYXNrQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnY3JlYXRlUHJvamVjdFRhc2snXSk7IC8vIFJlZHVuZGFudAogICAgICAgIFJvdXRlOjpnZXQoJ2dldC1wcm9qZWN0LXRhc2snLCBbVGFza0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2dldFByb2plY3RUYXNrJ10pOyAvLyBSZWR1bmRhbnQKICAgICAgICBSb3V0ZTo6cG9zdCgndXBkYXRlLXByb2plY3QtdGFzaycsIFtUYXNrQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAndXBkYXRlUHJvamVjdFRhc2snXSk7IC8vIFJlZHVuZGFudAogICAgICAgIFJvdXRlOjpwb3N0KCdkZWxldGUtcHJvamVjdC10YXNrJywgW1Rhc2tBcGlDb250cm9sbGVyOjpjbGFzcyAsICdkZWxldGVQcm9qZWN0VGFzayddKTsgLy8gUmVkdW5kYW50',
    'replace' => 'ICAgICAgICAvLyBUaGVzZSB0YWtlIHByb2plY3RfaWQgLyB0YXNrX2lkIGluIHRoZSBib2R5LCBzbyB0aGV5IG11c3Qgc3RheSBvbiB0aGUgbGVnYWN5IEFwaUNvbnRyb2xsZXIKICAgICAgICAvLyBtZXRob2RzOiB0aGUgUkVTVGZ1bCBjb250cm9sbGVyIG1ldGhvZHMgZXhwZWN0IHtpZH0gaW4gdGhlIFVSTCBhbmQgNTAwIHdpdGhvdXQgaXQuCiAgICAgICAgUm91dGU6OnBvc3QoJ3VwZGF0ZS1wcm9qZWN0JywgW0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ3VwZGF0ZVByb2plY3QnXSk7CiAgICAgICAgUm91dGU6OnBvc3QoJ2RlbGV0ZS1wcm9qZWN0JywgW0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2RlbGV0ZVByb2plY3QnXSk7CiAgICAgICAgUm91dGU6OnBvc3QoJ2R1cGxpY2F0ZS1wcm9qZWN0JywgW0FwaUNvbnRyb2xsZXI6OmNsYXNzICwgJ2R1cGxpY2F0ZVByb2plY3QnXSk7CiAgICAgICAgUm91dGU6OmdldCgnZ2V0LXByb2plY3QtdGFza3MnLCBbQXBpQ29udHJvbGxlcjo6Y2xhc3MgLCAnZ2V0UHJvamVjdFRhc2tzJ10pOwogICAgICAgIFJvdXRlOjpwb3N0KCdjcmVhdGUtcHJvamVjdC10YXNrJywgW1Rhc2tBcGlDb250cm9sbGVyOjpjbGFzcyAsICdjcmVhdGVQcm9qZWN0VGFzayddKTsgLy8gUmVkdW5kYW50CiAgICAgICAgUm91dGU6OmdldCgnZ2V0LXByb2plY3QtdGFzaycsIFtBcGlDb250cm9sbGVyOjpjbGFzcyAsICdnZXRQcm9qZWN0VGFzayddKTsKICAgICAgICBSb3V0ZTo6cG9zdCgndXBkYXRlLXByb2plY3QtdGFzaycsIFtBcGlDb250cm9sbGVyOjpjbGFzcyAsICd1cGRhdGVQcm9qZWN0VGFzayddKTsKICAgICAgICBSb3V0ZTo6cG9zdCgnZGVsZXRlLXByb2plY3QtdGFzaycsIFtBcGlDb250cm9sbGVyOjpjbGFzcyAsICdkZWxldGVQcm9qZWN0VGFzayddKTs=',
  ),
  8 => 
  array (
    'path' => 'app/Http/Controllers/Api/AttendanceApiController.php',
    'label' => 'markAttendance Utility::Utility',
    'find' => 'ICAgICAgICAkdGltZSA9IGRhdGUoJ0g6aTpzJyk7CgogICAgICAgIC8vIEdldCBjb21wYW55IHRpbWUgc2V0dGluZ3MKICAgICAgICAkc3RhcnRUaW1lID0gVXRpbGl0eTo6Z2V0VmFsQnlOYW1lKCdjb21wYW55X3N0YXJ0X3RpbWUnKTsKICAgICAgICAkZW5kVGltZSA9IFV0aWxpdHk6OlV0aWxpdHk6OmdldFZhbEJ5TmFtZSgnY29tcGFueV9lbmRfdGltZScpOw==',
    'replace' => 'ICAgICAgICAkdGltZSA9IGRhdGUoJ0g6aTpzJyk7CgogICAgICAgIC8vIEdldCBjb21wYW55IHRpbWUgc2V0dGluZ3MKICAgICAgICAkc3RhcnRUaW1lID0gVXRpbGl0eTo6Z2V0VmFsQnlOYW1lKCdjb21wYW55X3N0YXJ0X3RpbWUnKTsKICAgICAgICAkZW5kVGltZSA9IFV0aWxpdHk6OmdldFZhbEJ5TmFtZSgnY29tcGFueV9lbmRfdGltZScpOw==',
  ),
  9 => 
  array (
    'path' => 'app/Http/Controllers/Api/AttendanceApiController.php',
    'label' => 'bulk clock_out',
    'find' => 'ICAgICAgICAgICAgICAgICRjbG9ja091dCA9ICRjbG9ja091dCA9ICcwMDowMDowMCc7',
    'replace' => 'ICAgICAgICAgICAgICAgICRjbG9ja091dCA9ICRlbXBsb3llZURhdGFbJ2Nsb2NrX291dCddID8/ICcwMDowMDowMCc7',
  ),
  10 => 
  array (
    'path' => 'app/Http/Controllers/Api/AttendanceApiController.php',
    'label' => 'import/export methods',
    'find' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiBnZXRBdHRlbmRhbmNlRGV0YWlsKCRpZCkKICAgIHs=',
    'replace' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiBleHBvcnRBdHRlbmRhbmNlKCkKICAgIHsKICAgICAgICBpZiAoIUF1dGg6OnVzZXIoKS0+Y2FuKCdtYW5hZ2UgYXR0ZW5kYW5jZScpKSB7CiAgICAgICAgICAgIHJldHVybiAkdGhpcy0+ZXJyb3IoJ1Blcm1pc3Npb24gZGVuaWVkLicsIDQwMyk7CiAgICAgICAgfQoKICAgICAgICByZXR1cm4gXE1hYXR3ZWJzaXRlXEV4Y2VsXEZhY2FkZXNcRXhjZWw6OmRvd25sb2FkKG5ldyBcQXBwXEV4cG9ydHNcQXR0ZW5kYW5jZUV4cG9ydCgpLCAnYXR0ZW5kYW5jZV8nIC4gZGF0ZSgnWS1tLWRfSC1pLXMnKSAuICcueGxzeCcpOwogICAgfQoKICAgIC8qKgogICAgICogVXBsb2FkIGEgLmNzdi8ueGxzeCB3aG9zZSBmaXJzdCByb3cgaXMgYSBoZWFkZXIgd2l0aDogZW1haWwgKG9yIGVtcGxveWVlX2VtYWlsKSwKICAgICAqIGRhdGUsIGNsb2NrX2luLCBjbG9ja19vdXQuIE9uZSByb3cgcGVyIGVtcGxveWVlIHBlciBkYXk7IGFuIGV4aXN0aW5nIHJvdyBmb3IKICAgICAqIHRoYXQgZW1wbG95ZWUgYW5kIGRhdGUgaXMgb3ZlcndyaXR0ZW4uIFRoZSB3ZWIgaW1wb3J0ZXIgZG9lcyB0aGUgc2FtZSB0aGluZwogICAgICogdGhyb3VnaCBhIHNlc3Npb24tYmFja2VkIGNvbHVtbi1tYXBwaW5nIHNjcmVlbiwgd2hpY2ggYW4gQVBJIGNsaWVudCBjYW5ub3QgdXNlLgogICAgICovCiAgICBwdWJsaWMgZnVuY3Rpb24gaW1wb3J0QXR0ZW5kYW5jZShSZXF1ZXN0ICRyZXF1ZXN0KQogICAgewogICAgICAgIGlmICghQXV0aDo6dXNlcigpLT5jYW4oJ2NyZWF0ZSBhdHRlbmRhbmNlJykpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5lcnJvcignUGVybWlzc2lvbiBkZW5pZWQuJywgNDAzKTsKICAgICAgICB9CgogICAgICAgICR2YWxpZGF0b3IgPSBWYWxpZGF0b3I6Om1ha2UoJHJlcXVlc3QtPmFsbCgpLCBbCiAgICAgICAgICAgICdmaWxlJyA9PiAncmVxdWlyZWR8ZmlsZXxtaW1lczpjc3YsdHh0LHhsc3gseGxzfG1heDo1MTIwJywKICAgICAgICBdKTsKICAgICAgICBpZiAoJHZhbGlkYXRvci0+ZmFpbHMoKSkgewogICAgICAgICAgICByZXR1cm4gJHRoaXMtPmVycm9yKCR2YWxpZGF0b3ItPmVycm9ycygpLT5maXJzdCgpLCA0MjIpOwogICAgICAgIH0KCiAgICAgICAgJHNoZWV0cyA9IFxNYWF0d2Vic2l0ZVxFeGNlbFxGYWNhZGVzXEV4Y2VsOjp0b0FycmF5KG5ldyBjbGFzcyBpbXBsZW1lbnRzIFxNYWF0d2Vic2l0ZVxFeGNlbFxDb25jZXJuc1xUb0FycmF5IHsKICAgICAgICAgICAgcHVibGljIGZ1bmN0aW9uIGFycmF5KGFycmF5ICRyb3dzKSB7fQogICAgICAgIH0sICRyZXF1ZXN0LT5maWxlKCdmaWxlJykpOwogICAgICAgICRyb3dzID0gJHNoZWV0c1swXSA/PyBbXTsKICAgICAgICBpZiAoY291bnQoJHJvd3MpIDwgMikgewogICAgICAgICAgICByZXR1cm4gJHRoaXMtPmVycm9yKCdUaGUgZmlsZSBoYXMgbm8gZGF0YSByb3dzLicsIDQyMik7CiAgICAgICAgfQoKICAgICAgICAkaGVhZGVyID0gYXJyYXlfbWFwKGZuICgkaCkgPT4gc3RyX3JlcGxhY2UoWycgJywgJy0nXSwgJ18nLCBzdHJ0b2xvd2VyKHRyaW0oKHN0cmluZykgJGgpKSksIGFycmF5X3NoaWZ0KCRyb3dzKSk7CiAgICAgICAgJGNvbCA9IFsKICAgICAgICAgICAgJ2VtYWlsJyA9PiBhcnJheV9zZWFyY2goJ2VtYWlsJywgJGhlYWRlcikgIT09IGZhbHNlID8gYXJyYXlfc2VhcmNoKCdlbWFpbCcsICRoZWFkZXIpIDogYXJyYXlfc2VhcmNoKCdlbXBsb3llZV9lbWFpbCcsICRoZWFkZXIpLAogICAgICAgICAgICAnZGF0ZScgPT4gYXJyYXlfc2VhcmNoKCdkYXRlJywgJGhlYWRlciksCiAgICAgICAgICAgICdjbG9ja19pbicgPT4gYXJyYXlfc2VhcmNoKCdjbG9ja19pbicsICRoZWFkZXIpLAogICAgICAgICAgICAnY2xvY2tfb3V0JyA9PiBhcnJheV9zZWFyY2goJ2Nsb2NrX291dCcsICRoZWFkZXIpLAogICAgICAgIF07CiAgICAgICAgZm9yZWFjaCAoWydlbWFpbCcsICdkYXRlJywgJ2Nsb2NrX2luJ10gYXMgJHJlcXVpcmVkKSB7CiAgICAgICAgICAgIGlmICgkY29sWyRyZXF1aXJlZF0gPT09IGZhbHNlKSB7CiAgICAgICAgICAgICAgICByZXR1cm4gJHRoaXMtPmVycm9yKCJNaXNzaW5nIGNvbHVtbiAneyRyZXF1aXJlZH0nLiBFeHBlY3RlZCBoZWFkZXI6IGVtYWlsLCBkYXRlLCBjbG9ja19pbiwgY2xvY2tfb3V0IiwgNDIyKTsKICAgICAgICAgICAgfQogICAgICAgIH0KICAgICAgICBpZiAoY291bnQoJHJvd3MpID4gNTAwMCkgewogICAgICAgICAgICByZXR1cm4gJHRoaXMtPmVycm9yKCdUb28gbWFueSByb3dzIChtYXggNTAwMCBwZXIgZmlsZSkuJywgNDIyKTsKICAgICAgICB9CgogICAgICAgICRjcmVhdG9ySWQgPSBBdXRoOjp1c2VyKCktPmNyZWF0b3JJZCgpOwogICAgICAgICRlbXBsb3llZXMgPSBFbXBsb3llZTo6d2hlcmUoJ2NyZWF0ZWRfYnknLCAkY3JlYXRvcklkKS0+Z2V0KFsnaWQnLCAnZW1haWwnXSkKICAgICAgICAgICAgLT5rZXlCeShmbiAoJGUpID0+IHN0cnRvbG93ZXIodHJpbSgkZS0+ZW1haWwpKSk7CiAgICAgICAgJHN0YXJ0VGltZSA9IFV0aWxpdHk6OmdldFZhbEJ5TmFtZSgnY29tcGFueV9zdGFydF90aW1lJykgPzogJzA5OjAwOjAwJzsKICAgICAgICAkZW5kVGltZSA9IFV0aWxpdHk6OmdldFZhbEJ5TmFtZSgnY29tcGFueV9lbmRfdGltZScpID86ICcxODowMDowMCc7CgogICAgICAgICRpbXBvcnRlZCA9IDA7CiAgICAgICAgJGZhaWxlZCA9IFtdOwogICAgICAgIGZvcmVhY2ggKCRyb3dzIGFzICRpID0+ICRyb3cpIHsKICAgICAgICAgICAgJGxpbmUgPSAkaSArIDI7IC8vIDEtYmFzZWQsIGFmdGVyIHRoZSBoZWFkZXIKICAgICAgICAgICAgJGVtYWlsID0gc3RydG9sb3dlcih0cmltKChzdHJpbmcpICgkcm93WyRjb2xbJ2VtYWlsJ11dID8/ICcnKSkpOwogICAgICAgICAgICBpZiAoJGVtYWlsID09PSAnJyAmJiBlbXB0eShhcnJheV9maWx0ZXIoJHJvdykpKSB7CiAgICAgICAgICAgICAgICBjb250aW51ZTsKICAgICAgICAgICAgfQogICAgICAgICAgICAkZW1wbG95ZWUgPSAkZW1wbG95ZWVzLT5nZXQoJGVtYWlsKTsKICAgICAgICAgICAgJGRhdGUgPSAkdGhpcy0+aW1wb3J0RGF0ZSgkcm93WyRjb2xbJ2RhdGUnXV0gPz8gbnVsbCk7CiAgICAgICAgICAgICRjbG9ja0luID0gJHRoaXMtPmltcG9ydFRpbWUoJHJvd1skY29sWydjbG9ja19pbiddXSA/PyBudWxsKTsKICAgICAgICAgICAgJGNsb2NrT3V0ID0gJGNvbFsnY2xvY2tfb3V0J10gIT09IGZhbHNlID8gJHRoaXMtPmltcG9ydFRpbWUoJHJvd1skY29sWydjbG9ja19vdXQnXV0gPz8gbnVsbCkgOiAnMDA6MDA6MDAnOwoKICAgICAgICAgICAgaWYgKCEkZW1wbG95ZWUpIHsKICAgICAgICAgICAgICAgICRmYWlsZWRbXSA9IFsncm93JyA9PiAkbGluZSwgJ2VtYWlsJyA9PiAkZW1haWwsICdyZWFzb24nID0+ICdObyBlbXBsb3llZSB3aXRoIHRoaXMgZW1haWwgaW4geW91ciBjb21wYW55LiddOwogICAgICAgICAgICAgICAgY29udGludWU7CiAgICAgICAgICAgIH0KICAgICAgICAgICAgaWYgKCEkZGF0ZSB8fCAkY2xvY2tJbiA9PT0gbnVsbCB8fCAkY2xvY2tPdXQgPT09IG51bGwpIHsKICAgICAgICAgICAgICAgICRmYWlsZWRbXSA9IFsncm93JyA9PiAkbGluZSwgJ2VtYWlsJyA9PiAkZW1haWwsICdyZWFzb24nID0+ICdJbnZhbGlkIGRhdGUgb3IgdGltZS4nXTsKICAgICAgICAgICAgICAgIGNvbnRpbnVlOwogICAgICAgICAgICB9CgogICAgICAgICAgICAkdmFsdWVzID0gWwogICAgICAgICAgICAgICAgJ3N0YXR1cycgPT4gJGNsb2NrSW4gIT09ICcwMDowMDowMCcgPyAnUHJlc2VudCcgOiAnTGVhdmUnLAogICAgICAgICAgICAgICAgJ2Nsb2NrX2luJyA9PiAkY2xvY2tJbiwKICAgICAgICAgICAgICAgICdjbG9ja19vdXQnID0+ICRjbG9ja091dCwKICAgICAgICAgICAgICAgICdsYXRlJyA9PiAkY2xvY2tJbiAhPT0gJzAwOjAwOjAwJyA/ICR0aGlzLT5zZWNvbmRzVG9UaW1lKHN0cnRvdGltZSgiJGRhdGUgJGNsb2NrSW4iKSAtIHN0cnRvdGltZSgiJGRhdGUgJHN0YXJ0VGltZSIpKSA6ICcwMDowMDowMCcsCiAgICAgICAgICAgICAgICAnZWFybHlfbGVhdmluZycgPT4gJGNsb2NrT3V0ICE9PSAnMDA6MDA6MDAnID8gJHRoaXMtPnNlY29uZHNUb1RpbWUoc3RydG90aW1lKCIkZGF0ZSAkZW5kVGltZSIpIC0gc3RydG90aW1lKCIkZGF0ZSAkY2xvY2tPdXQiKSkgOiAnMDA6MDA6MDAnLAogICAgICAgICAgICAgICAgJ292ZXJ0aW1lJyA9PiAkY2xvY2tPdXQgIT09ICcwMDowMDowMCcgPyAkdGhpcy0+c2Vjb25kc1RvVGltZShzdHJ0b3RpbWUoIiRkYXRlICRjbG9ja091dCIpIC0gc3RydG90aW1lKCIkZGF0ZSAkZW5kVGltZSIpKSA6ICcwMDowMDowMCcsCiAgICAgICAgICAgIF07CgogICAgICAgICAgICAkYXR0ZW5kYW5jZSA9IEF0dGVuZGFuY2VFbXBsb3llZTo6d2hlcmUoJ2VtcGxveWVlX2lkJywgJGVtcGxveWVlLT5pZCktPndoZXJlKCdkYXRlJywgJGRhdGUpLT5maXJzdCgpOwogICAgICAgICAgICBpZiAoJGF0dGVuZGFuY2UpIHsKICAgICAgICAgICAgICAgICRhdHRlbmRhbmNlLT51cGRhdGUoJHZhbHVlcyk7CiAgICAgICAgICAgIH0gZWxzZSB7CiAgICAgICAgICAgICAgICAkYXR0ZW5kYW5jZSA9IG5ldyBBdHRlbmRhbmNlRW1wbG95ZWUoKTsKICAgICAgICAgICAgICAgICRhdHRlbmRhbmNlLT5mb3JjZUZpbGwoJHZhbHVlcyArIFsKICAgICAgICAgICAgICAgICAgICAnZW1wbG95ZWVfaWQnID0+ICRlbXBsb3llZS0+aWQsCiAgICAgICAgICAgICAgICAgICAgJ2RhdGUnID0+ICRkYXRlLAogICAgICAgICAgICAgICAgICAgICd0b3RhbF9yZXN0JyA9PiAnMDA6MDA6MDAnLAogICAgICAgICAgICAgICAgICAgICdjcmVhdGVkX2J5JyA9PiAkY3JlYXRvcklkLAogICAgICAgICAgICAgICAgXSktPnNhdmUoKTsKICAgICAgICAgICAgfQogICAgICAgICAgICAkaW1wb3J0ZWQrKzsKICAgICAgICB9CgogICAgICAgIHJldHVybiAkdGhpcy0+c3VjY2VzcyhbJ2ltcG9ydGVkJyA9PiAkaW1wb3J0ZWQsICdmYWlsZWQnID0+ICRmYWlsZWRdLCAiSW1wb3J0ZWQgeyRpbXBvcnRlZH0gcm93KHMpLCAiIC4gY291bnQoJGZhaWxlZCkgLiAnIGZhaWxlZC4nKTsKICAgIH0KCiAgICBwcml2YXRlIGZ1bmN0aW9uIGltcG9ydERhdGUoJHZhbHVlKQogICAgewogICAgICAgIGlmICgkdmFsdWUgPT09IG51bGwgfHwgJHZhbHVlID09PSAnJykgewogICAgICAgICAgICByZXR1cm4gbnVsbDsKICAgICAgICB9CiAgICAgICAgaWYgKGlzX251bWVyaWMoJHZhbHVlKSkgeyAvLyBFeGNlbCBzZXJpYWwgZGF0ZQogICAgICAgICAgICByZXR1cm4gXFBocE9mZmljZVxQaHBTcHJlYWRzaGVldFxTaGFyZWRcRGF0ZTo6ZXhjZWxUb0RhdGVUaW1lT2JqZWN0KCR2YWx1ZSktPmZvcm1hdCgnWS1tLWQnKTsKICAgICAgICB9CiAgICAgICAgJHRzID0gc3RydG90aW1lKChzdHJpbmcpICR2YWx1ZSk7CgogICAgICAgIHJldHVybiAkdHMgPyBkYXRlKCdZLW0tZCcsICR0cykgOiBudWxsOwogICAgfQoKICAgIC8vIEVtcHR5IGNlbGwgPSBubyBjbG9jayAoMDA6MDA6MDApOyB1bnBhcnNlYWJsZSA9IG51bGwuCiAgICBwcml2YXRlIGZ1bmN0aW9uIGltcG9ydFRpbWUoJHZhbHVlKQogICAgewogICAgICAgIGlmICgkdmFsdWUgPT09IG51bGwgfHwgdHJpbSgoc3RyaW5nKSAkdmFsdWUpID09PSAnJykgewogICAgICAgICAgICByZXR1cm4gJzAwOjAwOjAwJzsKICAgICAgICB9CiAgICAgICAgaWYgKGlzX251bWVyaWMoJHZhbHVlKSAmJiAkdmFsdWUgPCAxKSB7IC8vIEV4Y2VsIHRpbWUgZnJhY3Rpb24gb2YgYSBkYXkKICAgICAgICAgICAgcmV0dXJuIGdtZGF0ZSgnSDppOnMnLCAoaW50KSByb3VuZCgkdmFsdWUgKiA4NjQwMCkpOwogICAgICAgIH0KICAgICAgICAkdHMgPSBzdHJ0b3RpbWUoJzIwMDAtMDEtMDEgJyAuIHRyaW0oKHN0cmluZykgJHZhbHVlKSk7CgogICAgICAgIHJldHVybiAkdHMgPyBkYXRlKCdIOmk6cycsICR0cykgOiBudWxsOwogICAgfQoKICAgIHByaXZhdGUgZnVuY3Rpb24gc2Vjb25kc1RvVGltZSgkc2Vjb25kcykKICAgIHsKICAgICAgICAkc2Vjb25kcyA9IG1heCgoaW50KSAkc2Vjb25kcywgMCk7CgogICAgICAgIHJldHVybiBzcHJpbnRmKCclMDJkOiUwMmQ6JTAyZCcsIGZsb29yKCRzZWNvbmRzIC8gMzYwMCksIGZsb29yKCRzZWNvbmRzIC8gNjAgJSA2MCksICRzZWNvbmRzICUgNjApOwogICAgfQoKICAgIHB1YmxpYyBmdW5jdGlvbiBnZXRBdHRlbmRhbmNlRGV0YWlsKCRpZCkKICAgIHs=',
  ),
  11 => 
  array (
    'path' => 'app/Http/Controllers/Api/ProjectApiController.php',
    'label' => 'uploadProjectFile',
    'find' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiBnZXRQcm9qZWN0TWVtYmVycygkaWQpCiAgICB7',
    'replace' => 'ICAgIC8qKgogICAgICogUHJvamVjdCBmaWxlcyBhcmUgdGhlIGF0dGFjaG1lbnRzIG9mIHRoZSBwcm9qZWN0J3MgdGFza3MgKFByb2plY3Q6OnByb2plY3RBdHRhY2htZW50cyksCiAgICAgKiBzbyBhbiB1cGxvYWQgaGFzIHRvIG5hbWUgdGhlIHRhc2sgaXQgYmVsb25ncyB0by4KICAgICAqLwogICAgcHVibGljIGZ1bmN0aW9uIHVwbG9hZFByb2plY3RGaWxlKFJlcXVlc3QgJHJlcXVlc3QsICRpZCkKICAgIHsKICAgICAgICAkcHJvamVjdCA9IFByb2plY3Q6OndoZXJlKCdpZCcsICRpZCktPndoZXJlKCdjcmVhdGVkX2J5JywgQXV0aDo6dXNlcigpLT5jcmVhdG9ySWQoKSktPmZpcnN0KCk7CiAgICAgICAgaWYgKCEkcHJvamVjdCkgcmV0dXJuICR0aGlzLT5lcnJvcignUHJvamVjdCBub3QgZm91bmQuJywgNDA0KTsKCiAgICAgICAgJHZhbGlkYXRvciA9IFZhbGlkYXRvcjo6bWFrZSgkcmVxdWVzdC0+YWxsKCksIFsKICAgICAgICAgICAgJ3Rhc2tfaWQnID0+ICdyZXF1aXJlZHxpbnRlZ2VyJywKICAgICAgICAgICAgJ2ZpbGUnID0+ICdyZXF1aXJlZHxmaWxlJywKICAgICAgICBdKTsKICAgICAgICBpZiAoJHZhbGlkYXRvci0+ZmFpbHMoKSkgcmV0dXJuICR0aGlzLT5lcnJvcigkdmFsaWRhdG9yLT5lcnJvcnMoKS0+Zmlyc3QoKSwgNDIyKTsKCiAgICAgICAgaWYgKCFQcm9qZWN0VGFzazo6d2hlcmUoJ2lkJywgJHJlcXVlc3QtPnRhc2tfaWQpLT53aGVyZSgncHJvamVjdF9pZCcsICRwcm9qZWN0LT5pZCktPmV4aXN0cygpKSB7CiAgICAgICAgICAgIHJldHVybiAkdGhpcy0+ZXJyb3IoJ1RoZSBzZWxlY3RlZCB0YXNrIGRvZXMgbm90IGJlbG9uZyB0byB0aGlzIHByb2plY3QuJywgNDIyKTsKICAgICAgICB9CgogICAgICAgIHJldHVybiBhcHAoVGFza0FwaUNvbnRyb2xsZXI6OmNsYXNzKS0+dXBsb2FkVGFza0ZpbGUoJHJlcXVlc3QsICRyZXF1ZXN0LT50YXNrX2lkKTsKICAgIH0KCiAgICBwdWJsaWMgZnVuY3Rpb24gZ2V0UHJvamVjdE1lbWJlcnMoJGlkKQogICAgew==',
  ),
  12 => 
  array (
    'path' => 'app/Http/Controllers/Api/TaskApiController.php',
    'label' => 'task check-in/out tracker',
    'find' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiBzdGFydFRpbWVUcmFja2VyKCRpZCkKICAgIHsKICAgICAgICAkdGFzayA9IFByb2plY3RUYXNrOjpmaW5kKCRpZCk7CiAgICAgICAgaWYgKCEkdGFzaykgcmV0dXJuICR0aGlzLT5lcnJvcignVGFzayBub3QgZm91bmQuJywgNDA0KTsKCiAgICAgICAgJHRyYWNrZXIgPSBuZXcgXEFwcFxNb2RlbHNcVGltZVRyYWNrZXIoKTsKICAgICAgICAkdHJhY2tlci0+cHJvamVjdF9pZCA9ICR0YXNrLT5wcm9qZWN0X2lkOwogICAgICAgICR0cmFja2VyLT50YXNrX2lkID0gJGlkOwogICAgICAgICR0cmFja2VyLT5uYW1lID0gJHRhc2stPm5hbWU7CiAgICAgICAgJHRyYWNrZXItPnVzZXJfaWQgPSBBdXRoOjp1c2VyKCktPmlkOwogICAgICAgICR0cmFja2VyLT5jcmVhdGVkX2J5ID0gQXV0aDo6dXNlcigpLT5jcmVhdG9ySWQoKTsKICAgICAgICAkdHJhY2tlci0+c3RhcnRfdGltZSA9IGRhdGUoJ1ktbS1kIEg6aTpzJyk7CiAgICAgICAgJHRyYWNrZXItPnNhdmUoKTsKCiAgICAgICAgcmV0dXJuICR0aGlzLT5zdWNjZXNzKFsndHJhY2tlcicgPT4gJHRyYWNrZXJdLCAnVGltZSB0cmFja2luZyBzdGFydGVkLicpOwogICAgfQoKICAgIHB1YmxpYyBmdW5jdGlvbiBzdG9wVGltZVRyYWNrZXIoJGlkKQogICAgewogICAgICAgICR0cmFja2VyID0gXEFwcFxNb2RlbHNcVGltZVRyYWNrZXI6OndoZXJlKCd0YXNrX2lkJywgJGlkKQogICAgICAgICAgICAtPndoZXJlKCd1c2VyX2lkJywgQXV0aDo6dXNlcigpLT5pZCkKICAgICAgICAgICAgLT53aGVyZU51bGwoJ2VuZF90aW1lJykKICAgICAgICAgICAgLT5maXJzdCgpOwoKICAgICAgICBpZiAoISR0cmFja2VyKSByZXR1cm4gJHRoaXMtPmVycm9yKCdObyBhY3RpdmUgdHJhY2tlciBmb3VuZCBmb3IgdGhpcyB0YXNrLicsIDQwNCk7CgogICAgICAgICR0cmFja2VyLT5lbmRfdGltZSA9IGRhdGUoJ1ktbS1kIEg6aTpzJyk7CiAgICAgICAgJHRyYWNrZXItPnRvdGFsX3RpbWUgPSBcQXBwXE1vZGVsc1xVdGlsaXR5OjpkaWZmYW5jZV90b190aW1lKCR0cmFja2VyLT5zdGFydF90aW1lLCAkdHJhY2tlci0+ZW5kX3RpbWUpOyAvLyBBc3N1bWluZyBVdGlsaXR5IGhlbHBlciBleGlzdHMKICAgICAgICAkdHJhY2tlci0+c2F2ZSgpOwoKICAgICAgICByZXR1cm4gJHRoaXMtPnN1Y2Nlc3MoWyd0cmFja2VyJyA9PiAkdHJhY2tlcl0sICdUaW1lIHRyYWNraW5nIHN0b3BwZWQuJyk7CiAgICB9Cgo=',
    'replace' => 'ICAgIC8qKgogICAgICogQWxzbyBzZXJ2ZXMgUE9TVCAvdGFza3Mve2lkfS9jaGVjay1pbi4gU2FtZSBydWxlcyBhcyAvdGltZS10cmFja2VyL3N0YXJ0IGFuZCB0aGUKICAgICAqIHdlYiB0cmFja2VyOiBvbmUgcnVubmluZyB0aW1lciBwZXIgdXNlciBhdCBhIHRpbWUsIHdoYXRldmVyIHRhc2sgaXQgaXMgb24uCiAgICAgKi8KICAgIHB1YmxpYyBmdW5jdGlvbiBzdGFydFRpbWVUcmFja2VyKCRpZCkKICAgIHsKICAgICAgICAkdGFzayA9ICR0aGlzLT5jb21wYW55VGFzaygkaWQpOwogICAgICAgIGlmICghJHRhc2spIHJldHVybiAkdGhpcy0+ZXJyb3IoJ1Rhc2sgbm90IGZvdW5kLicsIDQwNCk7CgogICAgICAgICRhY3RpdmUgPSBcQXBwXE1vZGVsc1xUaW1lVHJhY2tlcjo6d2hlcmUoJ3VzZXJfaWQnLCBBdXRoOjp1c2VyKCktPmlkKQogICAgICAgICAgICAtPndoZXJlKCdjcmVhdGVkX2J5JywgQXV0aDo6dXNlcigpLT5jcmVhdG9ySWQoKSkKICAgICAgICAgICAgLT53aGVyZU51bGwoJ2VuZF90aW1lJykKICAgICAgICAgICAgLT5maXJzdCgpOwogICAgICAgIGlmICgkYWN0aXZlKSB7CiAgICAgICAgICAgICRtZXNzYWdlID0gJGFjdGl2ZS0+dGFza19pZCA9PSAkdGFzay0+aWQKICAgICAgICAgICAgICAgID8gJ1lvdSBhcmUgYWxyZWFkeSBjaGVja2VkIGluIHRvIHRoaXMgdGFzay4nCiAgICAgICAgICAgICAgICA6ICdZb3UgYXJlIGNoZWNrZWQgaW4gdG8gYW5vdGhlciB0YXNrLiBDaGVjayBvdXQgb2YgaXQgZmlyc3QuJzsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5lcnJvcigkbWVzc2FnZSwgNDA5LCBbJ2FjdGl2ZV90cmFja2VyJyA9PiAkYWN0aXZlXSk7CiAgICAgICAgfQoKICAgICAgICAkdHJhY2tlciA9IG5ldyBcQXBwXE1vZGVsc1xUaW1lVHJhY2tlcigpOwogICAgICAgICR0cmFja2VyLT5wcm9qZWN0X2lkID0gJHRhc2stPnByb2plY3RfaWQ7CiAgICAgICAgJHRyYWNrZXItPnRhc2tfaWQgPSAkdGFzay0+aWQ7CiAgICAgICAgJHRyYWNrZXItPm5hbWUgPSAkdGFzay0+bmFtZTsKICAgICAgICAkdHJhY2tlci0+dXNlcl9pZCA9IEF1dGg6OnVzZXIoKS0+aWQ7CiAgICAgICAgJHRyYWNrZXItPmNyZWF0ZWRfYnkgPSBBdXRoOjp1c2VyKCktPmNyZWF0b3JJZCgpOwogICAgICAgICR0cmFja2VyLT5zdGFydF90aW1lID0gZGF0ZSgnWS1tLWQgSDppOnMnKTsKICAgICAgICAkdHJhY2tlci0+aXNfYWN0aXZlID0gMTsKICAgICAgICAkdHJhY2tlci0+c2F2ZSgpOwoKICAgICAgICByZXR1cm4gJHRoaXMtPnN1Y2Nlc3MoWyd0cmFja2VyJyA9PiAkdHJhY2tlcl0sICdUaW1lIHRyYWNraW5nIHN0YXJ0ZWQuJyk7CiAgICB9CgogICAgLyoqIEFsc28gc2VydmVzIFBPU1QgL3Rhc2tzL3tpZH0vY2hlY2stb3V0LiAqLwogICAgcHVibGljIGZ1bmN0aW9uIHN0b3BUaW1lVHJhY2tlcigkaWQpCiAgICB7CiAgICAgICAgJHRhc2sgPSAkdGhpcy0+Y29tcGFueVRhc2soJGlkKTsKICAgICAgICBpZiAoISR0YXNrKSByZXR1cm4gJHRoaXMtPmVycm9yKCdUYXNrIG5vdCBmb3VuZC4nLCA0MDQpOwoKICAgICAgICAkdHJhY2tlciA9IFxBcHBcTW9kZWxzXFRpbWVUcmFja2VyOjp3aGVyZSgndGFza19pZCcsICR0YXNrLT5pZCkKICAgICAgICAgICAgLT53aGVyZSgndXNlcl9pZCcsIEF1dGg6OnVzZXIoKS0+aWQpCiAgICAgICAgICAgIC0+d2hlcmVOdWxsKCdlbmRfdGltZScpCiAgICAgICAgICAgIC0+Zmlyc3QoKTsKCiAgICAgICAgaWYgKCEkdHJhY2tlcikgcmV0dXJuICR0aGlzLT5lcnJvcignTm8gYWN0aXZlIHRyYWNrZXIgZm91bmQgZm9yIHRoaXMgdGFzay4nLCA0MDQpOwoKICAgICAgICAkdHJhY2tlci0+ZW5kX3RpbWUgPSBkYXRlKCdZLW0tZCBIOmk6cycpOwogICAgICAgICR0cmFja2VyLT50b3RhbF90aW1lID0gXEFwcFxNb2RlbHNcVXRpbGl0eTo6ZGlmZmFuY2VfdG9fdGltZSgkdHJhY2tlci0+c3RhcnRfdGltZSwgJHRyYWNrZXItPmVuZF90aW1lKTsKICAgICAgICAkdHJhY2tlci0+aXNfYWN0aXZlID0gMDsgLy8gdGhlIHdlYiB0cmFja2VyIHJlYWRzIGlzX2FjdGl2ZSwgbm90IGVuZF90aW1lCiAgICAgICAgJHRyYWNrZXItPnNhdmUoKTsKCiAgICAgICAgcmV0dXJuICR0aGlzLT5zdWNjZXNzKFsndHJhY2tlcicgPT4gJHRyYWNrZXJdLCAnVGltZSB0cmFja2luZyBzdG9wcGVkLicpOwogICAgfQoKICAgIHByaXZhdGUgZnVuY3Rpb24gY29tcGFueVRhc2soJGlkKQogICAgewogICAgICAgICR0YXNrID0gUHJvamVjdFRhc2s6OmZpbmQoJGlkKTsKICAgICAgICBpZiAoISR0YXNrIHx8ICFQcm9qZWN0Ojp3aGVyZSgnaWQnLCAkdGFzay0+cHJvamVjdF9pZCktPndoZXJlKCdjcmVhdGVkX2J5JywgQXV0aDo6dXNlcigpLT5jcmVhdG9ySWQoKSktPmV4aXN0cygpKSB7CiAgICAgICAgICAgIHJldHVybiBudWxsOwogICAgICAgIH0KCiAgICAgICAgcmV0dXJuICR0YXNrOwogICAgfQoK',
  ),
  13 => 
  array (
    'path' => 'app/Http/Controllers/Api/TimeTrackerApiController.php',
    'label' => 'stop clears is_active',
    'find' => 'ICAgICAgICAkdHJhY2tlci0+dG90YWxfdGltZSA9IFV0aWxpdHk6OmRpZmZhbmNlX3RvX3RpbWUoJHRyYWNrZXItPnN0YXJ0X3RpbWUsICR0cmFja2VyLT5lbmRfdGltZSk7CiAgICAgICAgJHRyYWNrZXItPnNhdmUoKTs=',
    'replace' => 'ICAgICAgICAkdHJhY2tlci0+dG90YWxfdGltZSA9IFV0aWxpdHk6OmRpZmZhbmNlX3RvX3RpbWUoJHRyYWNrZXItPnN0YXJ0X3RpbWUsICR0cmFja2VyLT5lbmRfdGltZSk7CiAgICAgICAgJHRyYWNrZXItPmlzX2FjdGl2ZSA9IDA7IC8vIHRoZSB3ZWIgdHJhY2tlciByZWFkcyBpc19hY3RpdmUsIG5vdCBlbmRfdGltZQogICAgICAgICR0cmFja2VyLT5zYXZlKCk7',
  ),
  14 => 
  array (
    'path' => 'app/Models/TimeTracker.php',
    'label' => 'project/task/user relations',
    'find' => 'ICAgICAgICByZXR1cm4gJHRvdGFsID8gJHRvdGFsIDogJzAwOjAwOjAwJzsKICAgIH0KCg==',
    'replace' => 'ICAgICAgICByZXR1cm4gJHRvdGFsID8gJHRvdGFsIDogJzAwOjAwOjAwJzsKICAgIH0KCiAgICBwdWJsaWMgZnVuY3Rpb24gcHJvamVjdCgpCiAgICB7CiAgICAgICAgcmV0dXJuICR0aGlzLT5iZWxvbmdzVG8oUHJvamVjdDo6Y2xhc3MsICdwcm9qZWN0X2lkJyk7CiAgICB9CgogICAgcHVibGljIGZ1bmN0aW9uIHRhc2soKQogICAgewogICAgICAgIHJldHVybiAkdGhpcy0+YmVsb25nc1RvKFByb2plY3RUYXNrOjpjbGFzcywgJ3Rhc2tfaWQnKTsKICAgIH0KCiAgICBwdWJsaWMgZnVuY3Rpb24gdXNlcigpCiAgICB7CiAgICAgICAgcmV0dXJuICR0aGlzLT5iZWxvbmdzVG8oVXNlcjo6Y2xhc3MsICd1c2VyX2lkJyk7CiAgICB9Cg==',
  ),
  15 => 
  array (
    'path' => 'app/Http/Controllers/ApiController.php',
    'label' => 'updateProject guard',
    'find' => 'ICAgICAgICAkcHJvamVjdCA9IFByb2plY3Q6OmZpbmQoJHJlcXVlc3QtPnByb2plY3RfaWQpOwoKICAgICAgICAvLyBQZXJtaXNzaW9uIGNoZWNrIGNhbiBiZSBhZGRlZCBoZXJl',
    'replace' => 'ICAgICAgICBpZiAoISR0aGlzLT5vd25zUHJvamVjdCgkcmVxdWVzdC0+cHJvamVjdF9pZCkpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5lcnJvcignUHJvamVjdCBub3QgZm91bmQuJywgNDA0KTsKICAgICAgICB9CgogICAgICAgICRwcm9qZWN0ID0gUHJvamVjdDo6ZmluZCgkcmVxdWVzdC0+cHJvamVjdF9pZCk7',
  ),
  16 => 
  array (
    'path' => 'app/Http/Controllers/ApiController.php',
    'label' => 'deleteProject guard',
    'find' => 'ICAgICAgICBQcm9qZWN0OjpkZWxldGVQcm9qZWN0KCRyZXF1ZXN0LT5wcm9qZWN0X2lkKTs=',
    'replace' => 'ICAgICAgICBpZiAoISR0aGlzLT5vd25zUHJvamVjdCgkcmVxdWVzdC0+cHJvamVjdF9pZCkpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5lcnJvcignUHJvamVjdCBub3QgZm91bmQuJywgNDA0KTsKICAgICAgICB9CgogICAgICAgIFByb2plY3Q6OmRlbGV0ZVByb2plY3QoJHJlcXVlc3QtPnByb2plY3RfaWQpOw==',
  ),
  17 => 
  array (
    'path' => 'app/Http/Controllers/ApiController.php',
    'label' => 'duplicateProject guard',
    'find' => 'ICAgICAgICAkcHJvamVjdCA9IFByb2plY3Q6OmZpbmQoJHJlcXVlc3QtPnByb2plY3RfaWQpOwogICAgICAgICRkdXBsaWNhdGUgPSBuZXcgUHJvamVjdCgpOw==',
    'replace' => 'ICAgICAgICBpZiAoISR0aGlzLT5vd25zUHJvamVjdCgkcmVxdWVzdC0+cHJvamVjdF9pZCkpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5lcnJvcignUHJvamVjdCBub3QgZm91bmQuJywgNDA0KTsKICAgICAgICB9CgogICAgICAgICRwcm9qZWN0ID0gUHJvamVjdDo6ZmluZCgkcmVxdWVzdC0+cHJvamVjdF9pZCk7CiAgICAgICAgJGR1cGxpY2F0ZSA9IG5ldyBQcm9qZWN0KCk7',
  ),
  18 => 
  array (
    'path' => 'app/Http/Controllers/ApiController.php',
    'label' => 'getProjectTasks guard',
    'find' => 'ICAgICAgICAkdXNlciA9IGF1dGgoKS0+dXNlcigpOwogICAgICAgICRwcm9qZWN0X2lkID0gJHJlcXVlc3QtPnByb2plY3RfaWQ7',
    'replace' => 'ICAgICAgICBpZiAoISR0aGlzLT5vd25zUHJvamVjdCgkcmVxdWVzdC0+cHJvamVjdF9pZCkpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5lcnJvcignUHJvamVjdCBub3QgZm91bmQuJywgNDA0KTsKICAgICAgICB9CgogICAgICAgICR1c2VyID0gYXV0aCgpLT51c2VyKCk7CiAgICAgICAgJHByb2plY3RfaWQgPSAkcmVxdWVzdC0+cHJvamVjdF9pZDs=',
  ),
  19 => 
  array (
    'path' => 'app/Http/Controllers/ApiController.php',
    'label' => 'getProjectTask guard',
    'find' => 'ICAgICAgICAkdGFzayA9IFByb2plY3RUYXNrOjpmaW5kKCRyZXF1ZXN0LT50YXNrX2lkKTsKCiAgICAgICAgcmV0dXJuICR0aGlzLT5zdWNjZXNzKFsndGFzaycgPT4gJHRhc2tdLCAnR2V0IFByb2plY3QgVGFzayBzdWNjZXNzZnVsbHkuJyk7',
    'replace' => 'ICAgICAgICBpZiAoISR0aGlzLT5vd25zVGFzaygkcmVxdWVzdC0+dGFza19pZCkpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5lcnJvcignVGFzayBub3QgZm91bmQuJywgNDA0KTsKICAgICAgICB9CgogICAgICAgICR0YXNrID0gUHJvamVjdFRhc2s6OmZpbmQoJHJlcXVlc3QtPnRhc2tfaWQpOwoKICAgICAgICByZXR1cm4gJHRoaXMtPnN1Y2Nlc3MoWyd0YXNrJyA9PiAkdGFza10sICdHZXQgUHJvamVjdCBUYXNrIHN1Y2Nlc3NmdWxseS4nKTs=',
  ),
  20 => 
  array (
    'path' => 'app/Http/Controllers/ApiController.php',
    'label' => 'updateProjectTask guard',
    'find' => 'ICAgICAgICAkdGFzayA9IFByb2plY3RUYXNrOjpmaW5kKCRyZXF1ZXN0LT50YXNrX2lkKTsKICAgICAgICAkcG9zdCA9ICRyZXF1ZXN0LT5hbGwoKTs=',
    'replace' => 'ICAgICAgICBpZiAoISR0aGlzLT5vd25zVGFzaygkcmVxdWVzdC0+dGFza19pZCkpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5lcnJvcignVGFzayBub3QgZm91bmQuJywgNDA0KTsKICAgICAgICB9CgogICAgICAgICR0YXNrID0gUHJvamVjdFRhc2s6OmZpbmQoJHJlcXVlc3QtPnRhc2tfaWQpOwogICAgICAgICRwb3N0ID0gJHJlcXVlc3QtPmFsbCgpOw==',
  ),
  21 => 
  array (
    'path' => 'app/Http/Controllers/ApiController.php',
    'label' => 'deleteProjectTask guard + helpers',
    'find' => 'ICAgICAgICBQcm9qZWN0VGFzazo6ZGVsZXRlVGFzayhbJHJlcXVlc3QtPnRhc2tfaWRdKTsKCiAgICAgICAgcmV0dXJuICR0aGlzLT5zdWNjZXNzKFtdLCAnVGFzayBEZWxldGVkIHN1Y2Nlc3NmdWxseS4nKTsKICAgIH0KfQ==',
    'replace' => 'ICAgICAgICBpZiAoISR0aGlzLT5vd25zVGFzaygkcmVxdWVzdC0+dGFza19pZCkpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5lcnJvcignVGFzayBub3QgZm91bmQuJywgNDA0KTsKICAgICAgICB9CgogICAgICAgIFByb2plY3RUYXNrOjpkZWxldGVUYXNrKFskcmVxdWVzdC0+dGFza19pZF0pOwoKICAgICAgICByZXR1cm4gJHRoaXMtPnN1Y2Nlc3MoW10sICdUYXNrIERlbGV0ZWQgc3VjY2Vzc2Z1bGx5LicpOwogICAgfQoKICAgIC8vIFRoZSBsZWdhY3kgcm91dGVzIHZhbGlkYXRlIGlkcyB3aXRoIGEgYmFyZSBleGlzdHM6IHJ1bGUsIHdoaWNoIGFjY2VwdHMgYW55IGNvbXBhbnkncyByb3dzLgogICAgcHJpdmF0ZSBmdW5jdGlvbiBvd25zUHJvamVjdCgkcHJvamVjdElkKQogICAgewogICAgICAgIHJldHVybiBQcm9qZWN0Ojp3aGVyZSgnaWQnLCAkcHJvamVjdElkKS0+d2hlcmUoJ2NyZWF0ZWRfYnknLCBBdXRoOjp1c2VyKCktPmNyZWF0b3JJZCgpKS0+ZXhpc3RzKCk7CiAgICB9CgogICAgcHJpdmF0ZSBmdW5jdGlvbiBvd25zVGFzaygkdGFza0lkKQogICAgewogICAgICAgICR0YXNrID0gUHJvamVjdFRhc2s6OmZpbmQoJHRhc2tJZCk7CgogICAgICAgIHJldHVybiAkdGFzayAmJiAkdGhpcy0+b3duc1Byb2plY3QoJHRhc2stPnByb2plY3RfaWQpOwogICAgfQp9',
  ),
);
$classes = array (
  0 => 'App\\Models\\TimeTracker',
  1 => 'App\\Http\\Controllers\\Api\\TimeTrackerApiController',
  2 => 'App\\Http\\Controllers\\ApiController',
  3 => 'App\\Http\\Controllers\\Api\\AttendanceApiController',
  4 => 'App\\Http\\Controllers\\Api\\ProjectApiController',
  5 => 'App\\Http\\Controllers\\Api\\TaskApiController',
  6 => 'App\\Http\\Controllers\\Api\\SupportRequestApiController',
  7 => 'App\\Http\\Controllers\\Api\\DocumentApiController',
);
$requiredNewFiles = array (
  'app/Http/Controllers/Api/SupportRequestApiController.php' => '4a8eabd66d393d4b',
  'app/Http/Controllers/Api/DocumentApiController.php' => '17dfb9a45f95fe2f',
);
$backupPrefix = 'api-routes-fix';

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

// The new controllers are uploaded by hand before this runs; the routes below point at them.
foreach ($requiredNewFiles as $rel => $hash) {
    $full = $root . '/' . $rel;
    if (!is_file($full)) {
        echo "[FAIL] $rel -- missing. Upload it first (it is a new file).\n";
        echo "\nNOTHING WAS WRITTEN.\n";
        exit(2);
    }
    if (h16(file_get_contents($full)) !== $hash) {
        echo "[FAIL] $rel -- present but not the expected version (" . h16(file_get_contents($full)) . " != $hash). Re-upload it.\n";
        echo "\nNOTHING WAS WRITTEN.\n";
        exit(2);
    }
    echo "[ OK ] $rel present\n";
}
echo "\n";

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
    $tmp = $full . '.tmp-api-routes-fix';

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
    $probe = $root . '/storage/api-routes-fix-probe.php';
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
echo "  php artisan route:list --path=api/v1/support-requests\n";
echo "Then call GET /api/v1/support-requests and GET /api/v1/trainings/types with a token.\n";
echo "To undo: php " . basename(__FILE__) . " --rollback\n";
exit(0);
