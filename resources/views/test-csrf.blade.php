<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test CSRF Token - GestPro</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 0;
        }
        button:hover {
            background-color: #0056b3;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #007bff;
            background-color: #f8f9fa;
        }
        .success {
            border-left-color: #28a745;
            background-color: #d4edda;
        }
        .error {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test CSRF Token - GestPro</h1>
        <p>Esta página te permite probar la funcionalidad del CSRF token y el login.</p>
        
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" value="admin@example.com" placeholder="Ingresa tu email">
        </div>
        
        <div class="form-group">
            <label for="password">Contraseña:</label>
            <input type="password" id="password" value="password" placeholder="Ingresa tu contraseña">
        </div>
        
        <button onclick="testCSRF()">🔐 Probar CSRF Token y Login</button>
        <button onclick="testCSRFOnly()">🛡️ Solo Probar CSRF Token</button>
        <button onclick="testCSRFSimple()">🧪 Probar Ruta Simple CSRF</button>
        <button onclick="testCSRFBody()">📝 Probar CSRF en Body</button>
        <button onclick="testAPILogin()">🚀 Probar Login API (Sin CSRF)</button>
        <button onclick="clearResults()">🗑️ Limpiar Resultados</button>
        
        <div id="result" class="result" style="display: none;"></div>
    </div>

    <script>
        async function testCSRF() {
            const resultDiv = document.getElementById('result');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.innerHTML = '<p>🔄 Probando CSRF token y login...</p>';
            
            try {
                // 1. Obtener CSRF token
                const csrfResponse = await fetch('/sanctum/csrf-cookie', {
                    method: 'GET',
                    credentials: 'include'
                });
                
                if (csrfResponse.ok) {
                    resultDiv.innerHTML = '<p style="color: green;">✅ CSRF token obtenido exitosamente</p>';
                    
                    // Obtener el token de las cookies
                    const cookies = document.cookie;
                    const csrfToken = cookies.split('XSRF-TOKEN=')[1]?.split(';')[0];
                    
                    if (csrfToken) {
                        resultDiv.innerHTML += `<p>🔑 Token encontrado: ${csrfToken}</p>`;
                    }
                    
                    // 2. Intentar hacer login
                    const email = document.getElementById('email').value;
                    const password = document.getElementById('password').value;
                    
                    const loginResponse = await fetch('/GestPro/login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': decodeURIComponent(csrfToken || ''),
                            'X-XSRF-TOKEN': decodeURIComponent(csrfToken || '')
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            email: email,
                            password: password
                        })
                    });
                    
                    const loginResult = await loginResponse.json();
                    
                    if (loginResponse.ok) {
                        resultDiv.className = 'result success';
                        resultDiv.innerHTML += `<p>✅ Login exitoso: ${JSON.stringify(loginResult)}</p>`;
                    } else {
                        resultDiv.className = 'result error';
                        resultDiv.innerHTML += `<p>❌ Error en login: ${JSON.stringify(loginResult)}</p>`;
                    }
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = '<p>❌ Error obteniendo CSRF token</p>';
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `<p>❌ Error: ${error.message}</p>`;
            }
        }
        
        async function testCSRFOnly() {
            const resultDiv = document.getElementById('result');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.innerHTML = '<p>🔄 Probando solo CSRF token...</p>';
            
            try {
                const csrfResponse = await fetch('/sanctum/csrf-cookie', {
                    method: 'GET',
                    credentials: 'include'
                });
                
                if (csrfResponse.ok) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = '<p>✅ CSRF token obtenido exitosamente</p>';
                    
                    // Mostrar las cookies
                    const cookies = document.cookie;
                    resultDiv.innerHTML += `<p><strong>Cookies actuales:</strong></p>`;
                    resultDiv.innerHTML += `<pre style="background: #f1f1f1; padding: 10px; border-radius: 4px; overflow-x: auto;">${cookies}</pre>`;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = '<p>❌ Error obteniendo CSRF token</p>';
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `<p>❌ Error: ${error.message}</p>`;
            }
        }
        
        function clearResults() {
            const resultDiv = document.getElementById('result');
            resultDiv.style.display = 'none';
            resultDiv.innerHTML = '';
        }
        
        async function testCSRFSimple() {
            const resultDiv = document.getElementById('result');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.innerHTML = '<p>🔄 Probando ruta simple CSRF...</p>';
            
            try {
                // 1. Obtener CSRF token
                const csrfResponse = await fetch('/sanctum/csrf-cookie', {
                    method: 'GET',
                    credentials: 'include'
                });
                
                if (csrfResponse.ok) {
                    resultDiv.innerHTML = '<p style="color: green;">✅ CSRF token obtenido exitosamente</p>';
                    
                    // Obtener el token de las cookies
                    const cookies = document.cookie;
                    const csrfToken = cookies.split('XSRF-TOKEN=')[1]?.split(';')[0];
                    
                    if (csrfToken) {
                        resultDiv.innerHTML += `<p>🔑 Token encontrado: ${csrfToken}</p>`;
                    }
                    
                    // 2. Probar ruta simple
                    const simpleResponse = await fetch('/test-csrf-simple', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': decodeURIComponent(csrfToken || ''),
                            'X-XSRF-TOKEN': decodeURIComponent(csrfToken || '')
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            test: 'data',
                            timestamp: new Date().toISOString()
                        })
                    });
                    
                    const simpleResult = await simpleResponse.json();
                    
                    if (simpleResponse.ok) {
                        resultDiv.className = 'result success';
                        resultDiv.innerHTML += `<p>✅ Ruta simple exitosa: ${JSON.stringify(simpleResult)}</p>`;
                    } else {
                        resultDiv.className = 'result error';
                        resultDiv.innerHTML += `<p>❌ Error en ruta simple: ${JSON.stringify(simpleResult)}</p>`;
                    }
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = '<p>❌ Error obteniendo CSRF token</p>';
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `<p>❌ Error: ${error.message}</p>`;
            }
        }
        
        async function testCSRFBody() {
            const resultDiv = document.getElementById('result');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.innerHTML = '<p>🔄 Probando CSRF en Body...</p>';

            try {
                // 1. Obtener CSRF token
                const csrfResponse = await fetch('/sanctum/csrf-cookie', {
                    method: 'GET',
                    credentials: 'include'
                });

                if (csrfResponse.ok) {
                    resultDiv.innerHTML = '<p style="color: green;">✅ CSRF token obtenido exitosamente</p>';
                    
                    // Obtener el token de las cookies
                    const cookies = document.cookie;
                    const csrfToken = cookies.split('XSRF-TOKEN=')[1]?.split(';')[0];
                    
                    if (csrfToken) {
                        resultDiv.innerHTML += `<p>🔑 Token encontrado: ${csrfToken}</p>`;
                    }

                    // 2. Probar ruta simple con CSRF en Body
                    const simpleResponse = await fetch('/test-csrf-simple', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            test: 'data',
                            timestamp: new Date().toISOString(),
                            _token: csrfToken // Incluir el token en el body
                        })
                    });
                    
                    const simpleResult = await simpleResponse.json();
                    
                    if (simpleResponse.ok) {
                        resultDiv.className = 'result success';
                        resultDiv.innerHTML += `<p>✅ Ruta simple con CSRF en Body exitosa: ${JSON.stringify(simpleResult)}</p>`;
                    } else {
                        resultDiv.className = 'result error';
                        resultDiv.innerHTML += `<p>❌ Error en ruta simple con CSRF en Body: ${JSON.stringify(simpleResult)}</p>`;
                    }
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = '<p>❌ Error obteniendo CSRF token</p>';
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `<p>❌ Error: ${error.message}</p>`;
            }
        }
        
        async function testAPILogin() {
            const resultDiv = document.getElementById('result');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.innerHTML = '<p>🔄 Probando login API (sin CSRF)...</p>';
            
            try {
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                
                const loginResponse = await fetch('/api/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password
                    })
                });
                
                const loginResult = await loginResponse.json();
                
                if (loginResponse.ok) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `<p>✅ Login API exitoso: ${JSON.stringify(loginResult)}</p>`;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `<p>❌ Error en login API: ${JSON.stringify(loginResult)}</p>`;
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `<p>❌ Error: ${error.message}</p>`;
            }
        }
    </script>
</body>
</html> 