<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (Notification.permission === "default") {
            // Browsers block automatic prompts, so we create a temporary button!
            let btn = document.createElement('button');
            btn.innerHTML = '🔔 Enable Notifications';
            btn.style.position = 'fixed';
            btn.style.bottom = '20px';
            btn.style.right = '20px';
            btn.style.padding = '10px 15px';
            btn.style.background = '#4F46E5';
            btn.style.color = 'white';
            btn.style.border = 'none';
            btn.style.borderRadius = '5px';
            btn.style.zIndex = '9999';
            btn.style.cursor = 'pointer';
            btn.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
            
            btn.onclick = function() {
                requestPushPermission();
                btn.remove();
            };
            
            document.body.appendChild(btn);
        } else if (Notification.permission === "granted") {
            subscribeUser();
        }
    });

    function requestPushPermission() {
        Notification.requestPermission().then((permission) => {
            if (permission === 'granted') {
                console.log('Notification permission granted.');
                subscribeUser();
            } else {
                console.warn('Notification permission denied.');
            }
        });
    }

    function subscribeUser() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('Push messaging is not supported in this browser.');
            return;
        }

        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('Service Worker registered with scope:', registration.scope);
                return registration.pushManager.getSubscription()
                    .then(function(subscription) {
                        if (subscription) {
                            return subscription;
                        }
                        
                        const vapidPublicKey = "{{ env('VAPID_PUBLIC_KEY') }}";
                        const convertedVapidKey = urlBase64ToUint8Array(vapidPublicKey);
                        
                        return registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: convertedVapidKey
                        });
                    });
            })
            .then(function(subscription) {
                // Send subscription to backend
                sendSubscriptionToBackend(subscription);
            })
            .catch(function(error) {
                console.error('Service Worker or Push Subscription error:', error);
            });
    }

    function sendSubscriptionToBackend(subscription) {
        fetch('/push/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(subscription)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to save subscription on backend');
            }
            console.log('Push subscription saved successfully.');
        })
        .catch(error => {
            console.error('Error saving subscription:', error);
        });
    }

    // Utility function to convert VAPID key
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
</script>
