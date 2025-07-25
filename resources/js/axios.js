import axios from "axios";
import Cookies from "js-cookie";

if (process.env.NODE_ENV === "development") {
    axios.defaults.baseURL = "http://localhost:8000";
} else {
    axios.defaults.baseURL = "https://ingeer.co/GestPro";
}

axios.defaults.withCredentials = true;

    // Este interceptor añade el token CSRF manualmente
axios.interceptors.request.use((config) => {
    const token = Cookies.get("XSRF-TOKEN");
    if (token) {
        config.headers["X-XSRF-TOKEN"] = decodeURIComponent(token);
    }
    return config;
});

export default axios;
