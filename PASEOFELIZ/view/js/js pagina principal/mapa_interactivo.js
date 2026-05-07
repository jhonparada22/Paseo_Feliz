// Inicializar mapa centrado en Cúcuta
const map = L.map('map').setView([7.8939, -72.5078], 14);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

let marcadorPaseador = L.marker([0,0]).addTo(map).bindPopup("Ubicación del Paseador");
let puntosRuta = [];
let polyline = L.polyline([], {color: 'blue'}).addTo(map);

// FUNCIÓN PARA RASTREAR EL TELÉFONO
function empezarRuta() {
    if ("geolocation" in navigator) {
        navigator.geolocation.watchPosition(position => {
            const { latitude, longitude } = position.coords;
            const nuevaPos = [latitude, longitude];

            // 1. Mover el marcador
            marcadorPaseador.setLatLng(nuevaPos);
            map.panTo(nuevaPos);

            // 2. Dibujar la ruta
            puntosRuta.push(nuevaPos);
            polyline.setLatLngs(puntosRuta);

            // 3. ENVIAR A LA BASE DE DATOS (InfinityFree)
            enviarUbicacionAServidor(latitude, longitude);

        }, error => console.error(error), { enableHighAccuracy: true });
    }
}

// FUNCIÓN PARA GUARDAR LUGARES AL HACER CLIC
map.on('click', function(e) {
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;
    
    L.marker([lat, lng]).addTo(map)
        .bindPopup("Punto de interés guardado")
        .openPopup();
    
    // Aquí conectarías con tu PHP para hacer el INSERT en MySQL
    console.log(`Lugar guardado: ${lat}, ${lng}`);
});

function enviarUbicacionAServidor(lat, lng) {
    // Aquí usarías fetch() para enviar los datos a un archivo .php
    // que haga el UPDATE en tu tabla 'rastreo_paseador'
}