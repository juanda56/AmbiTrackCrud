  // Import the functions you need from the SDKs you need
  import { initializeApp } from "https://www.gstatic.com/firebasejs/12.6.0/firebase-app.js";
  import { getAnalytics } from "https://www.gstatic.com/firebasejs/12.6.0/firebase-analytics.js";
  // TODO: Add SDKs for Firebase products that you want to use
  // https://firebase.google.com/docs/web/setup#available-libraries
  // Your web app's Firebase configuration
  // For Firebase JS SDK v7.20.0 and later, measurementId is optional
  const firebaseConfig = {
    apiKey: "AIzaSyAUEB0F38oZQAuIN2SY7uvkiFyB-Gvilm8",
    authDomain: "ambitrack-reporting-system.firebaseapp.com",
    projectId: "ambitrack-reporting-system",
    storageBucket: "ambitrack-reporting-system.firebasestorage.app",
    messagingSenderId: "1003055418894",
    appId: "1:1003055418894:web:36fca84eda850a76e8f65f",
    measurementId: "G-WX5T8DTDK6"
  };
  // Initialize Firebase
  const app = initializeApp(firebaseConfig);
  const analytics = getAnalytics(app);