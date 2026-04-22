# 4 Básico A POS

Sistema POS en PHP + MySQL.

## Requisitos

- PHP 8.0+ con `pdo_mysql`
- MySQL/MariaDB
- Servidor web (Apache o Nginx)

## Despliegue en carpeta `/4a`

### 1) Copiar archivos

Publica todo el contenido del proyecto dentro de la ruta web:

- Ejemplo: `public_html/4a/`

El archivo principal debe quedar en:

- `public_html/4a/index.php`

### 2) Configurar base de datos

Edita `.env` con credenciales reales:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=tu_bd
DB_USER=tu_usuario
DB_PASS=tu_password
```

Si tu hosting/FTP no sube archivos ocultos (como `.env`), puedes crear este archivo alternativo:

- `config/env.local`

Con el mismo contenido:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=tu_bd
DB_USER=tu_usuario
DB_PASS=tu_password
```

El sistema ahora intenta leer, en orden:

- `.env`
- `.env.local`
- `config/.env`
- `config/env.local`

Esto evita que use `root` por defecto cuando faltan variables en producción.
Además, si `DB_HOST=localhost` falla por socket, la conexión ahora intenta automáticamente `127.0.0.1` usando `DB_PORT`.

### Archivos de conectividad ya generados

Este proyecto ya incluye copias de conectividad listas para despliegue:

- `.env.local`
- `config/.env`
- `config/env.local`

### Verificación rápida de conexión

Puedes abrir o ejecutar:

- `healthcheck.php`

Muestra variables activas (sin revelar contraseña) y estado de conexión a MySQL.

### Verificación extendida de host

También tienes:

- `healthcheck_extended.php`

Este script prueba automáticamente varios hosts en orden (`DB_HOST`, `localhost`, `127.0.0.1` y candidatos personalizados) y reporta cuál responde.

Puedes agregar candidatos extra en `.env`:

```env
DB_HOST_CANDIDATES=localhost,127.0.0.1,mysql.tudominio.com
```

### 3) Inicializar tablas

Abre en el navegador:

- `https://tu-dominio.com/4a/install.php`

Cuando termine, elimina `install.php` y `temp_install.php` por seguridad.

### 4) Verificar rutas en `/4a`

Este proyecto usa rutas relativas (`products.php`, `start_shift_api.php`, etc.), por lo que funciona dentro de subcarpetas como `/4a` sin reescrituras adicionales.

### 5) Acceder al sistema

- Vendedor (POS): `https://tu-dominio.com/4a/vendedor_login.php`
- Admin: `https://tu-dominio.com/4a/admin_login.php`

También puedes abrir directamente `index.php`: si no hay sesión activa, redirige al acceso de vendedor.

### Usuarios por defecto (instalación nueva)

- Admin: `admin` / `franenro_admin123`
- Vendedor (principal): `ventas` / `ventas123`
- Vendedor (compatibilidad): `vendedor` / `vendedor123`

Se recomienda cambiar ambas claves tras instalar.

## Notas

- `admin.php` ahora detecta automáticamente la base de la ruta actual (`<base href="...">`) para evitar problemas al mover el proyecto a otra carpeta web.
- `index.php` también detecta automáticamente la base de la ruta actual para funcionar correctamente dentro de subcarpetas como `/4a`.
- En el POS (`index.php`), productos con `stock_level <= 0` se muestran al final de la lista para mejorar rapidez de selección en móvil.
- En `admin.php`, inicio/cierre de turno y reinicio usan modal propio (sin `alert/confirm/prompt` del navegador).
- Si usas caché del navegador, limpia caché después del despliegue.

## Correo automático al cerrar turno

Al cerrar un turno desde el POS/Admin, `end_shift_api.php` envía un correo con:

- Asunto: `cierre del turno X` (ejemplo: `cierre del turno 3`).
- Resumen de ventas del turno: cantidad de ventas, total vendido, gastos/devoluciones, caja esperada/final, diferencia.
- Desglose por método de pago.

Destino por defecto:

- `carlosdiazc@gmail.com`

Opcionalmente puedes configurar en `.env`:

```env
SHIFT_REPORT_EMAIL=carlosdiazc@gmail.com
SHIFT_REPORT_FROM=no-reply@tu-dominio.com
```

Importante: el envío usa la función nativa `mail()` de PHP, por lo que tu servidor debe tener servicio de correo saliente configurado para que Gmail reciba los mensajes.

### Robustez y monitoreo implementado

- Cada cierre de turno crea un registro en `notification_logs`.
- El sistema intenta enviar correo con reintentos automáticos.
- Si falla, el registro queda en estado `failed` para reintento posterior.
- El cierre de turno no se pierde: si el correo falla, el turno igual se cierra y queda trazabilidad.

### Panel de control resumido

Abre:

- `https://tu-dominio.com/4a/monitor.php`

Allí puedes ver puntos importantes del día:

- Turnos abiertos/cerrados.
- Ventas y monto vendido del día.
- Correos enviados/fallidos.
- Historial de notificaciones con intentos y errores.

También puedes usar el botón **Reintentar fallidos**, que llama a:

- `retry_notifications_api.php`

### Monitores útiles agregados

`monitor.php` ahora incluye un bloque adicional de monitores en tiempo real con:

- Ingreso neto del día (ventas - devoluciones - otros gastos).
- Otros gastos del día, ticket promedio y ventas de la última hora.
- Alertas de stock bajo/sin stock.
- Top productos vendidos del día.
- Últimos otros gastos registrados.
- Tendencia neta de los últimos 7 días.

Endpoint interno usado por esta sección:

- `get_monitor_summary_api.php`

## Reinicio operativo (cerrar turnos y partir desde cero)

Desde `admin.php` tienes el bloque **Reinicio operativo (día nuevo)** para:

- Cerrar todos los turnos abiertos.
- Reponer stock por ventas completadas (considerando devoluciones registradas).
- Eliminar `sales`, `sale_items` y `expenses` para empezar con ventas nuevas.
- Vaciar también `products` si mantienes activa la casilla correspondiente (viene activada por defecto).
- Abrir automáticamente un turno nuevo (opcional) con efectivo inicial.

### Cómo usarlo

1. Entra a `https://tu-dominio.com/4a/admin.php`
2. (Opcional) Ingresa el efectivo inicial del nuevo turno.
3. Pulsa **Cerrar turnos y reiniciar ventas**.
4. Ingresa la clave de seguridad `250012` y confirma la acción.

### Endpoint usado

- `reset_operations_api.php` (requiere sesión activa y usuario con rol `admin`).

## Insights en tiempo real en Admin

Dentro de `admin.php` ahora tienes el acceso **Insights en tiempo real**, donde puedes:

- Ver ingresos netos con descuento de **otros gastos**.
- Registrar **nota + monto** de otros gastos (se descuentan automáticamente).
- Revisar ranking de **productos más vendidos** y **menos vendidos**.
- Actualización automática cada 15 segundos.

Endpoint usado para el panel:

- `get_admin_realtime_summary_api.php`
