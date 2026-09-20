#!/usr/bin/env bash
# Recorrido HTTP del piloto: login de verdad, pantallas y privacidad.
set -u

HOST="${1:?falta el subdominio del cliente}"
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
  --data-urlencode "email=duena@${HOST%%.*}.test" \
  --data-urlencode "password=una-contrasena-larga-de-piloto")

check "el login responde con redireccion" "${RESP%%|*}" "302"

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
check "la sesion de este cliente no sirve en el demo" "$(codigo /panel demo.localhost)" "302"

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
echo "== G. Privacidad del bucket"
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
