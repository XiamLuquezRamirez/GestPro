import axios from 'axios';
console.log(process.env.NODE_ENV);
if (process.env.NODE_ENV === 'development') {
    axios.defaults.baseURL = 'http://localhost:8000';
} else {
    axios.defaults.baseURL = 'https://ingeer.co/gestpro/public/';
}

axios.defaults.withCredentials = true;

export default axios;
