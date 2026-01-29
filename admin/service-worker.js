// service-worker.js
self.addEventListener('push', function(event) {
    var options = {
        body: event.data ? event.data.text() : 'មានជូនដំណឹងថ្មី',
        icon: '/images/icon.png', // កំណត់រូបតំណាងសម្រាប់ជូនដំណឹង
        badge: '/images/badge.png' // រូបតំណាងសម្រាប់ Badge (តម្រូវ)
    };

    event.waitUntil(
        self.registration.showNotification('ជូនដំណឹងថ្មី', options)
    );
});
