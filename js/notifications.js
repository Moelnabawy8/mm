// Notifications JavaScript
function markAllAsRead() {
    fetch('/notifications/mark-all-read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload page to update notification count
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}

function viewAllNotifications() {
    // For now, just close the dropdown
    // You can implement a full notifications page later
    alert('View all notifications page - to be implemented');
}

// Mark notification as read when clicked
document.addEventListener('DOMContentLoaded', function() {
    const notificationItems = document.querySelectorAll('.notification-item a');
    
    notificationItems.forEach(item => {
        item.addEventListener('click', function(e) {
            const notificationId = this.closest('.notification-item').dataset.id;
            
            if (notificationId) {
                fetch(`/notifications/${notificationId}/mark-read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .catch(error => console.error('Error:', error));
            }
        });
    });
});
