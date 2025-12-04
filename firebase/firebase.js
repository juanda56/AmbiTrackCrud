import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
import {
  getFirestore,
  collection,
  addDoc,
  onSnapshot,
  doc,
  updateDoc,
  deleteDoc,
  serverTimestamp,
} from "https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js";
const firebaseConfig = {
  apiKey: "AIzaSyAUEB0F38oZQAuIN2SY7uvkiFyB-Gvilm8",
  authDomain: "ambitrack-reporting-system.firebaseapp.com",
  projectId: "ambitrack-reporting-system",
  storageBucket: "ambitrack-reporting-system.firebasestorage.app",
  messagingSenderId: "1003055418894",
  appId: "1:1003055418894:web:36fca84eda850a76e8f65f",
  measurementId: "G-WX5T8DTDK6",
};
const app = initializeApp(firebaseConfig);
const db = getFirestore(app);
const colRef = collection(db, "reportes");
let userLat = null,
  userLng = null,
  map = null,
  marker = null;
const ubicacionStatus = document.getElementById("ubicacion-status");
const leafletScript = document.createElement("script");
leafletScript.src = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
leafletScript.onload = initGeo;
document.body.appendChild(leafletScript);
function initGeo() {
  if (!("geolocation" in navigator)) {
    ubicacionStatus.textContent = "Ubicación: no disponible en este navegador.";
    initMap(4.60971, -74.08175, false); // Fallback Bogotá
    return;
  }
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      userLat = pos.coords.latitude;
      userLng = pos.coords.longitude;
      ubicacionStatus.textContent = `Ubicación: ${userLat.toFixed(
        5
      )}, ${userLng.toFixed(5)}`;
      initMap(userLat, userLng, true);
    },
    (err) => {
      ubicacionStatus.textContent =
        "Ubicación: no disponible (" + err.message + ")";
      initMap(4.60971, -74.08175, false);
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
}
function initMap(lat, lng, placeMarker) {
  map = L.map("map").setView([lat, lng], 13);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "&copy; OpenStreetMap",
  }).addTo(map);
  if (placeMarker) {
    marker = L.marker([lat, lng])
      .addTo(map)
      .bindPopup("Tu ubicación")
      .openPopup();
  }
  // Permite ajustar la ubicación con clic
  map.on("click", (e) => {
    userLat = e.latlng.lat;
    userLng = e.latlng.lng;
    ubicacionStatus.textContent = `Ubicación: ${userLat.toFixed(
      5
    )}, ${userLng.toFixed(5)}`;
    if (!marker) marker = L.marker([userLat, userLng]).addTo(map);
    marker
      .setLatLng([userLat, userLng])
      .bindPopup("Ubicación seleccionada")
      .openPopup();
  });
}
// 3) Guardar reporte
const form = document.getElementById("reporte-form");
form.addEventListener("submit", async (e) => {
  e.preventDefault();
  const tipo = document.getElementById("tipo").value;
  const descripcion = document.getElementById("descripcion").value.trim();
  if (!descripcion) {
    alert("Agrega una descripción.");
    return;
  }
  if (userLat == null || userLng == null) {
    alert("Ubicación no disponible.");
    return;
  }
  await addDoc(colRef, {
    tipo,
    descripcion,
    lat: userLat,
    lng: userLng,
    createdAt: serverTimestamp(),
    updatedAt: serverTimestamp(),
  });
  document.getElementById("descripcion").value = "";
});
// 4) Listado y acciones CRUD
const lista = document.getElementById("lista");
onSnapshot(colRef, (snap) => {
  lista.innerHTML = "";
  if (snap.empty) {
    lista.innerHTML = "<p class='muted'>No hay reportes aún.</p>";
    return;
  }
  snap.forEach((d) => {
    const r = { id: d.id, ...d.data() };
    const card = document.createElement("div");
    card.className = "card";v
    card.innerHTML = `
          <div><strong>${r.tipo}</strong></div>
          <div>${r.descripcion}</div>
          <div class="muted">Ubicación: ${Number(r.lat).toFixed(5)}, ${Number(
      r.lng
    ).toFixed(5)}</div>
          <div class="row" style="margin-top:8px;">
            <button data-action="edit">Editar descripción</button>
            <button data-action="delete">Eliminar</button>
          </div>
        `;
    // Editar
    card
      .querySelector("[data-action='edit']")
      .addEventListener("click", async () => {
        const nueva = prompt("Nueva descripción:", r.descripcion || "");
        if (nueva == null) return;
        await updateDoc(doc(db, "reportes", r.id), {
          descripcion: nueva.trim(),
          updatedAt: new Date(),
        });
      });
    // Eliminar
    card
      .querySelector("[data-action='delete']")
      .addEventListener("click", async () => {
        if (!confirm("¿Eliminar este reporte?")) return;
        await deleteDoc(doc(db, "reportes", r.id));
      });
    lista.appendChild(card);
  });
});
// Cargar librerías externas (PDF, Excel) por CDN
const jsPDFScript = document.createElement("script");
jsPDFScript.src =
  "https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js";
document.body.appendChild(jsPDFScript);

const autoTableScript = document.createElement("script");
autoTableScript.src =
  "https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js";
document.body.appendChild(autoTableScript);

const xlsxScript = document.createElement("script");
xlsxScript.src =
  "https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js";
document.body.appendChild(xlsxScript);

// Utilidad: leer reportes actuales del DOM
function getReportesFromDOM() {
  const cards = [...document.querySelectorAll("#lista .card")];
  return cards.map((card) => {
    const tipo = card.querySelector("strong").textContent;
    const descripcion = card.querySelectorAll("div")[1].textContent;
    const ub = card
      .querySelector(".muted")
      .textContent.replace("Ubicación: ", "");
    return { tipo, descripcion, ubicacion: ub };
  });
}

// Exportar a PDF
document.getElementById("export-pdf").addEventListener("click", () => {
  const rows = getReportesFromDOM().map((r) => [
    r.tipo,
    r.descripcion,
    r.ubicacion,
  ]);
  if (rows.length === 0) {
    alert("No hay reportes para exportar.");
    return;
  }
  const { jsPDF } = window.jspdf || {};
  if (!jsPDF || !window.jspdf || !window.jspdf.jsPDF) {
    alert("Cargando librería PDF, intenta de nuevo en 1-2 segundos.");
    return;
  }
  const doc = new jsPDF();
  doc.setFontSize(14);
  doc.text("Reportes ambientales", 14, 16);
  doc.autoTable({
    head: [["Tipo", "Descripción", "Ubicación"]],
    body: rows,
    startY: 22,
  });
  doc.save("reportes.pdf");
});

// Exportar a Word (HTML → .doc, sin librerías)
function exportWordSimple(reportes) {
  if (reportes.length === 0) {
    alert("No hay reportes para exportar.");
    return;
  }
  let html = `
        <html xmlns:o='urn:schemas-microsoft-com:office:office'
              xmlns:w='urn:schemas-microsoft-com:office:word'
              xmlns='http://www.w3.org/TR/REC-html40'>
        <head><meta charset="utf-8"><title>Reportes</title></head>
        <body>
          <h2>Reportes ambientales</h2>
          <table border="1" style="border-collapse:collapse;">
            <tr><th>Tipo</th><th>Descripción</th><th>Ubicación</th></tr>
      `;
  reportes.forEach((r) => {
    html += `<tr>
          <td>${r.tipo}</td>
          <td>${r.descripcion}</td>
          <td>${r.ubicacion}</td>
        </tr>`;
  });
  html += `</table></body></html>`;

  const blob = new Blob([html], { type: "application/msword" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = "reportes.doc";
  a.click();
  URL.revokeObjectURL(url);
}

document.getElementById("export-word").addEventListener("click", () => {
  const data = getReportesFromDOM();
  exportWordSimple(data);
});

// Exportar a Excel
document.getElementById("export-excel").addEventListener("click", () => {
  const data = getReportesFromDOM();
  if (data.length === 0) {
    alert("No hay reportes para exportar.");
    return;
  }
  if (!window.XLSX) {
    alert("Cargando librería Excel, intenta de nuevo en 1-2 segundos.");
    return;
  }
  const ws = XLSX.utils.json_to_sheet(data);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Reportes");
  XLSX.writeFile(wb, "reportes.xlsx");
});
