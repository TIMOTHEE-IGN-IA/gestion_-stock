const CACHE_NAME = "nova-stock-v1";

const urlsToCache = [
  "/",
  "/index.php",
  "/auth/login.php",
  "/dashboard.php",
  "/nova.png",
  "/icon-192.png.png",
  "/icon-512.png.png",
  "/background1.jpg.png"
];

// INSTALL
self.addEventListener("install", event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

// FETCH
self.addEventListener("fetch", event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        return response || fetch(event.request);
      })
  );
});

// ACTIVATE (nettoyage anciens caches)
self.addEventListener("activate", event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      )
    )
  );
});
