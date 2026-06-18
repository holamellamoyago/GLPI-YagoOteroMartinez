# GLPI - Módulos Yago Otero Martínez

Plugins personalizados para GLPI 11.0.7.

## Entorno

| Componente | Detalle |
|------------|---------|
| GLPI | 11.0.7 (diouxx/glpi:latest) |
| URL local | http://localhost:81 |
| Docker | glpi_server (red default_glpi_network) |
| Desarrollo | Volumen vivo → cambios instantáneos en el contenedor |

## Estructura

```
Modulos-GLPI/
├── dashboard/     # Panel de control personalizado para GLPI
├── AGENTS.md      # Contexto para Hermes Agent
├── .gitignore
└── README.md
```

## Flujo de desarrollo

```bash
# Instalar un módulo
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:install --allow-superuser <modulo>

# Activar
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:activate --allow-superuser <modulo>

# Ver estado
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:list --allow-superuser
```
