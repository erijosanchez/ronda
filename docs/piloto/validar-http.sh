#!/usr/bin/env bash
# Recorrido HTTP del piloto: login de verdad, pantallas y privacidad.
set -u

HOST="${1:?falta el dominio del cliente, por ejemplo piloto1234.localhost}"
# La contrasena de la duena. Por defecto la del guion de validacion; para el
# cliente de demostracion es otra.
CLAVE="${2:-una-contrasena-larga-de-piloto}"
# El correo de la duena. Por defecto el que crea validar-piloto.php; el cliente
# de demostracion usa otro.
CORREO="${3:-duena@${1%%.*}.test}"
BASE="http://localhost:8000"
COOKIES="$(mktemp)"
FALLOS=0

ok()   { echo "   OK    $1"; }
mal()  { echo "   FALLA $1"; FALLOS=$((FALLOS+1)); }
check(){ [ "$2" = "$3" ] && ok "$1 ($2)" || mal "$1: esperaba $3, llego $2"; }

codigo() { # codigo <ruta> [host]
  curl -s -m 30 -o /dev/null -w "%{http_code}" -b "$COOKIES" -c "$COOKIES" \
    -H "Host: ${2:-$HOST}" "$BASE$1"
}

echo "== A. Antes de entrar"
check "el panel exige sesion" "$(codigo /panel)" "302"
check "los pendientes exigen sesion" "$(codigo /pendientes)" "302"
check "el plan exige sesion" "$(codigo /plan)" "302"

echo
echo "== B. Login real"
LOGIN_HTML=$(curl -s -m 30 -c "$COOKIES" -H "Host: $HOST" "$BASE/login")
TOKEN=$(echo "$LOGIN_HTML" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')

if [ -z "$TOKEN" ]; then
  mal "no se pudo leer el token CSRF del formulario de login"
else
  ok "el formulario de login trae su token CSRF"
fi

echo "$LOGIN_HTML" | grep -q 'manifest.webmanifest' \
  && ok "el login ofrece instalar la aplicacion (manifiesto presente)" \
  || mal "el login NO enlaza el manifiesto de la PWA"

RESP=$(curl -s -m 30 -o /dev/null -w "%{http_code}|%{redirect_url}" -b "$COOKIES" -c "$COOKIES" \
  -H "Host: $HOST" -X POST "$BASE/login" \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "email=$CORREO" \
  --data-urlencode "password=$CLAVE")

# No basta con el 302: un login FALLIDO tambien redirige, de vuelta al propio
# login. Lo que distingue a uno bueno es a donde manda. Sin esto, el guion daba
# por abierta una sesion que nunca se abrio, y despues culpaba a las pantallas.
DESTINO="${RESP#*|}"

case "$DESTINO" in
  *"/panel"*) ok "el login abre sesion y manda al panel" ;;
  *) mal "el login no abrio sesion (fue a: ${DESTINO:-sin redireccion})" ;;
esac

echo
echo "== C. Ya dentro"
check "panel" "$(codigo /panel)" "200"
check "pendientes" "$(codigo /pendientes)" "200"
check "plan y consumo" "$(codigo /plan)" "200"
check "asistente de arranque" "$(codigo /bienvenida)" "200"
check "sedes" "$(codigo /sedes)" "200"
check "plantillas" "$(codigo /plantillas)" "200"
check "programaciones" "$(codigo /programaciones)" "200"
check "revision" "$(codigo /revision)" "200"
check "notificaciones" "$(codigo /notificaciones)" "200"
check "exportaciones" "$(codigo /exportaciones)" "200"
check "usuarios" "$(codigo /usuarios)" "200"

echo
echo "== D. Frontera entre clientes"
# Se comprueba contra OTRO cliente. Si se esta validando el demo, no hay con
# quien comparar y se dice, en vez de dar por fallado algo que no se miro.
OTRO="${4:-demo.localhost}"

if [ "$OTRO" = "$HOST" ]; then
  echo "   --    omitida: se esta validando $HOST contra si mismo"
else
  check "la sesion de este cliente no sirve en $OTRO" "$(codigo /panel "$OTRO")" "302"
fi

echo
echo "== E. Dominio central"
check "portada" "$(codigo / localhost)" "200"
check "registro abierto" "$(codigo /registro localhost)" "200"
check "el panel no existe en el dominio central" "$(codigo /panel localhost)" "404"

echo
echo "== F. PWA"
for archivo in /manifest.webmanifest /sw.js /offline.html /icons/icon-192.png; do
  check "sirve $archivo" "$(codigo $archivo)" "200"
done

curl -s -m 30 -H "Host: $HOST" "$BASE/sw.js" | grep -q 'livewire' \
  && ok "el service worker excluye a Livewire de la cache" \
  || mal "el service worker NO excluye a Livewire"

curl -s -m 30 -H "Host: $HOST" "$BASE/sw.js" | grep -q 'evidencia' \
  && ok "y tampoco cachea la evidencia" \
  || mal "el service worker NO excluye la evidencia"

echo
echo "== G. Los estilos llegan de verdad"
# Un 200 no dice que la pantalla se vea: si el bundle esta sin reconstruir, el
# HTML trae clases que no existen en el CSS y la pagina sale desarmada. Paso
# dos veces; ahora se comprueba.
PORTADA=$(curl -s -m 30 -H "Host: $HOST" "$BASE/")
# Se usa solo la RUTA del enlace y se pide contra $BASE. El enlace absoluto lo
# arma Laravel con la cabecera Host que mandamos nosotros, que va sin puerto:
# seguirlo tal cual lleva al puerto 80 y parece que el CSS no existe. Pasó dos
# veces al depurar; el puerto lo perdía curl, no la aplicacion.
CSS_PATH=$(echo "$PORTADA" | grep -oE 'href="[^"]*\.css"' | head -1 | sed 's/href="//;s/"//' | sed 's|https\?://[^/]*||')

if [ -z "$CSS_PATH" ]; then
  mal "la portada no enlaza ninguna hoja de estilos"
else
  CSS=$(curl -s -m 30 -H "Host: $HOST" "$BASE$CSS_PATH")
  echo "$CSS" | grep -q 'max-w-5xl'     && ok "el CSS servido incluye las clases que usa la portada"     || mal "el CSS esta sin reconstruir: falta alguna clase de la portada (npm run build)"
fi

echo
echo "== H. Privacidad del bucket"
ANON=$(curl -s -m 20 -o /dev/null -w "%{http_code}" "http://localhost:9000/ronda-evidence/")
[ "$ANON" = "403" ] || [ "$ANON" = "404" ] \
  && ok "el bucket de evidencia no se lista sin credenciales ($ANON)" \
  || mal "el bucket responde $ANON a un anonimo"

echo
echo "----------------------------------------------------------------------"
if [ "$FALLOS" -eq 0 ]; then
  echo "HTTP: todo lo comprobado pasa."
else
  echo "HTTP: $FALLOS comprobacion(es) fallaron."
fi

rm -f "$COOKIES"
