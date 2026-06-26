# GLPI - Modulos Yago Otero Martinez

Plugins personalizados para GLPI 11.0.7.

## Entorno

| Componente | Detalle |
|------------|---------|
| GLPI | 11.0.7 (diouxx/glpi:latest) |
| URL local | http://localhost:81 |
| Docker | glpi_server (red default_glpi_network) |
| Desarrollo | Volumen vivo → cambios instantaneos en el contenedor |

## Plugins

### glpIA — Mejora de textos con IA en tickets

Anyade un boton "glpIA mejorar" bajo cada editor de texto en los tickets de GLPI. Al pulsarlo, envia el texto y el contexto completo del ticket a DeepSeek (u otra API compatible con OpenAI) y devuelve una version mejorada, mas profesional y detallada.

- **Configuracion**: Configuracion > glpIA (API key, modelo, prompt personalizable)
- **Modelo recomendado**: DeepSeek V4 Flash
- **Pagina standalone**: Menu glpIA > Nombre de menu

### dashboard — Panel de control personalizado

Panel de control con pilotos F1 y equipos para GLPI.

## Estructura

```
Modulos-GLPI/
├── plugins/
│   ├── glpia/         # Mejora de textos con IA
│   └── dashboard/     # Panel de control personalizado
├── AGENTS.md          # Contexto para Hermes Agent
├── .gitignore
└── README.md
```

## Flujo de desarrollo

```bash
# Instalar un modulo
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:install --allow-superuser <modulo>

# Activar
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:activate --allow-superuser <modulo>

# Ver estado
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:list --allow-superuser
```

## GLPI 11: Assets estaticos (JS/CSS)

GLPI 11 usa Symfony con document root en `public/`. Los archivos JS/CSS de plugins necesitan symlinks:

```bash
docker exec glpi_server mkdir -p //var/www/html/glpi/public/plugins/<plugin>
docker exec glpi_server ln -sf //var/www/html/glpi/plugins/<plugin>/js //var/www/html/glpi/public/plugins/<plugin>/js
docker exec glpi_server ln -sf //var/www/html/glpi/plugins/<plugin>/css //var/www/html/glpi/public/plugins/<plugin>/css
```
