/**
 * This is a service worker file, often used for features like:
 * - Caching assets for offline use.
 * - Handling push notifications.
 * - Proxying API requests to a backend to avoid CORS issues.
 *
 * This file is currently a placeholder.
 */

// A simple fetch event listener that passes requests through.
self.addEventListener('fetch', (event) => {
  event.respondWith(fetch(event.request));
});
