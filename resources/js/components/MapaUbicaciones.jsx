import React, { useMemo, useRef, useEffect } from 'react';
import { MapContainer, TileLayer, Marker, useMap } from 'react-leaflet';
import MarkerClusterGroup from 'react-leaflet-cluster';
import 'leaflet/dist/leaflet.css';
import 'react-leaflet-cluster/dist/assets/MarkerCluster.css';
import 'react-leaflet-cluster/dist/assets/MarkerCluster.Default.css';
import '../leafletIconFix';

const CENTRO_POR_DEFECTO = [6.2442, -75.5812];
const ZOOM_POR_DEFECTO = 9;

// Encuadra el mapa a todos los puntos existentes la primera vez que llegan datos.
// No se repite en renders posteriores para no pelear con la navegación del usuario.
const AjustarVistaInicial = ({ puntos }) => {
    const map = useMap();
    const ajustadoRef = useRef(false);

    useEffect(() => {
        if (!ajustadoRef.current && puntos.length > 0) {
            map.fitBounds(puntos.map(p => [p.lat, p.lng]), { maxZoom: 13, padding: [40, 40] });
            ajustadoRef.current = true;
        }
    }, [puntos]);

    return null;
};

// Encuadra el mapa en los puntos de un municipio específico cuando se selecciona
// una fila en la tabla "Distribución por municipio".
const CentradorMunicipio = ({ municipio, puntos }) => {
    const map = useMap();

    useEffect(() => {
        if (!municipio) return;
        const puntosDelMunicipio = puntos.filter(p => p.proyecto.descripcion_municipio === municipio);
        if (puntosDelMunicipio.length > 0) {
            map.fitBounds(puntosDelMunicipio.map(p => [p.lat, p.lng]), { maxZoom: 14, padding: [40, 40] });
        }
    }, [municipio]);

    return null;
};

const MapaUbicaciones = ({ proyectos, onProyectoClick, municipioResaltado }) => {
    const puntos = useMemo(() => (
        proyectos.flatMap(proyecto =>
            (proyecto.puntosUbicacion || []).map(punto => ({
                lat: parseFloat(punto.lat),
                lng: parseFloat(punto.lng),
                proyecto,
            }))
        )
    ), [proyectos]);

    return (
        <div className="dashboard-mapa-container">
            <MapContainer center={CENTRO_POR_DEFECTO} zoom={ZOOM_POR_DEFECTO} style={{ height: '420px', width: '100%' }}>
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                <AjustarVistaInicial puntos={puntos} />
                <CentradorMunicipio municipio={municipioResaltado} puntos={puntos} />
                <MarkerClusterGroup chunkedLoading>
                    {puntos.map((punto, index) => (
                        <Marker
                            key={index}
                            position={[punto.lat, punto.lng]}
                            eventHandlers={{ click: () => onProyectoClick(punto.proyecto) }}
                        />
                    ))}
                </MarkerClusterGroup>
            </MapContainer>
        </div>
    );
};

export default MapaUbicaciones;
