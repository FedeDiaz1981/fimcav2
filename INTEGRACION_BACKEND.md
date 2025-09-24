# Integración Backend PHP - Sistema de Reservas

## Descripción

Este documento explica cómo usar el sistema integrado de reservas que combina tu proyecto Astro con un backend PHP para manejar las reservas de cabañas.

## Estructura del Sistema

```
finca_2/fimcav2/
├── public/
│   └── api/
│       ├── reservas.php      # API principal para reservas
│       └── cabanas.php       # API para obtener cabañas
├── data/
│   └── db.sqlite            # Base de datos SQLite
├── setup/
│   ├── init.php             # Script de inicialización completo
│   ├── create_db.php        # Crear solo la base de datos
│   └── seed_cabins.php      # Poblar solo las cabañas
├── config.php               # Configuración de email
└── src/components/
    └── ReservaCabanas.astro # Componente actualizado
```

## Instalación y Configuración

### 1. Inicializar el Sistema

```bash
cd finca_2/fimcav2
php setup/init.php
```

Este script:
- Crea la base de datos SQLite
- Crea las tablas necesarias
- Pobla las cabañas por defecto
- Verifica la configuración

### 2. Configurar Email (Opcional)

El archivo `config.php` ya está configurado con tus credenciales de Gmail. Si necesitas cambiarlo:

```php
return [
    'SMTP_HOST' => 'smtp.gmail.com',
    'SMTP_PORT' => 465,
    'SMTP_USER' => 'tu-email@gmail.com',
    'SMTP_PASS' => 'tu-app-password',
    'SMTP_TO'   => 'email-destino@gmail.com',
    'FROM_NAME' => 'Tu Nombre',
];
```

### 3. Iniciar los Servidores

**Terminal 1 - Servidor PHP:**
```bash
cd finca_2/fimcav2
php -S localhost:8000 -t public/
```

**Terminal 2 - Servidor Astro:**
```bash
cd finca_2/fimcav2
npm run dev
```

## API Endpoints

### GET /api/reservas
Obtiene todas las cabañas y fechas no disponibles.

**Respuesta:**
```json
{
    "cabins": [
        {
            "id": "1",
            "name": "Cabaña Familiar 1",
            "capacity": 4
        }
    ],
    "unavailable": {
        "1": ["2024-01-15", "2024-01-16"],
        "2": ["2024-01-20"]
    }
}
```

### POST /api/reservas
Crea una nueva reserva.

**Payload:**
```json
{
    "cabinId": "1",
    "startISO": "2024-01-15",
    "endISO": "2024-01-17",
    "nombre": "Juan Pérez",
    "telefono": "+5491234567890",
    "email": "juan@email.com"
}
```

**Respuesta exitosa:**
```json
{
    "ok": true,
    "bookingId": 123,
    "message": "Reserva creada exitosamente"
}
```

### GET /api/cabanas
Obtiene solo la lista de cabañas.

## Base de Datos

### Tablas

**cabins:**
- `id` (TEXT PRIMARY KEY)
- `name` (TEXT)
- `capacity` (INTEGER)

**bookings:**
- `id` (INTEGER PRIMARY KEY AUTOINCREMENT)
- `cabinId` (TEXT)
- `startISO` (TEXT)
- `endISO` (TEXT)
- `nights` (INTEGER)
- `name` (TEXT)
- `phone` (TEXT)
- `mail` (TEXT)
- `created_at` (TEXT)

**unavailable:**
- `cabinId` (TEXT)
- `dateISO` (TEXT)
- PRIMARY KEY (cabinId, dateISO)

## Uso del Componente

El componente `ReservaCabanas.astro` ya está actualizado para usar la nueva API. Se puede usar así:

```astro
---
import ReservaCabanas from '../components/ReservaCabanas.astro';

// Datos estáticos (opcional)
const cabins = [
  { id: '1', name: 'Cabaña Familiar 1', capacity: 4 },
  { id: '2', name: 'Cabaña Familiar 2', capacity: 4 }
];

const unavailable = {
  '1': ['2024-01-15', '2024-01-16'],
  '2': ['2024-01-20']
};
---

<ReservaCabanas 
  cabins={cabins}
  unavailable={unavailable}
  apiUrl="/api/reservas"
/>
```

## Características

### ✅ Implementado
- ✅ API REST para reservas
- ✅ Base de datos SQLite
- ✅ Validación de fechas y disponibilidad
- ✅ Notificaciones por email
- ✅ CORS configurado
- ✅ Manejo de errores
- ✅ Integración con componente Astro

### 🔄 Funcionalidades
- **Reservas en tiempo real**: Las fechas se marcan como no disponibles inmediatamente
- **Validación completa**: Fechas, email, campos requeridos
- **Notificaciones**: Email automático al crear reserva
- **CORS**: Configurado para desarrollo local
- **Manejo de errores**: Respuestas JSON consistentes

## Solución de Problemas

### Error: "Base de datos no encontrada"
```bash
php setup/init.php
```

### Error: "CORS"
Verifica que ambos servidores estén corriendo:
- PHP: `http://localhost:8000`
- Astro: `http://localhost:4321`

### Error: "Email no enviado"
1. Verifica `config.php`
2. Verifica que PHPMailer esté en `phpmailer/src/`
3. Usa app-password de Gmail, no tu contraseña normal

### Error: "Fechas no disponibles"
El sistema verifica automáticamente la disponibilidad. Si una fecha ya está reservada, no se puede reservar nuevamente.

## Desarrollo

### Agregar nuevas cabañas
```bash
php setup/seed_cabins.php
```

### Ver reservas en la base de datos
```bash
sqlite3 data/db.sqlite "SELECT * FROM bookings;"
```

### Limpiar base de datos
```bash
rm data/db.sqlite
php setup/init.php
```

## Producción

Para producción:
1. Cambiar URLs de CORS en los archivos PHP
2. Usar HTTPS
3. Configurar servidor web (Apache/Nginx)
4. Configurar SSL para email
5. Hacer backup regular de la base de datos

---

¡El sistema está listo para usar! 🎉
