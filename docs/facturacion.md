# Encender el cobro con Culqi

Todo el cobro está construido y probado. Lo único que falta es la cuenta de
comercio. Este documento es lo que hay que hacer el día que exista.

## 1. Pedir las llaves

En el panel de Culqi, **Desarrollo → Llaves**. Hay dos juegos, prueba y
producción, y cada juego tiene dos llaves:

| Llave | Empieza por | Dónde va |
|---|---|---|
| Pública | `pk_test_` / `pk_live_` | Al navegador, en el formulario de tarjeta |
| Secreta | `sk_test_` / `sk_live_` | Solo al servidor. **Nunca** al repositorio |

## 2. Ponerlas en el `.env`

```dotenv
BILLING_GATEWAY=culqi
CULQI_PUBLIC_KEY=pk_test_...
CULQI_SECRET_KEY=sk_test_...
```

Y recargar: con Octane el proceso se queda con la configuración anterior.

```bash
docker compose exec app php artisan config:clear
docker compose restart app
```

Eso es todo. No hay ningún otro cambio: las Actions, el comando de renovación y
la pantalla `/plan` son los mismos con `manual` que con `culqi`.

## 3. Comprobar que responde, antes de cobrarle a nadie

```bash
docker compose exec app php artisan billing:renew --dry-run
```

Muestra qué periodos se emitirían y qué cobros se intentarían, sin tocar nada.

Con las llaves **de prueba** puestas, Culqi acepta tarjetas de prueba (las de su
documentación) y el cobro se ve en su panel en modo test. Recién después se
cambia a las llaves `live`.

## 4. Lo que todavía falta para cobrar de verdad

El cobro necesita una tarjeta guardada, y la tarjeta se guarda desde un
formulario con Culqi.js que **aún no existe**: no se puede construir ni probar
sin una llave pública real, porque el token lo emite Culqi contra el navegador.

Cuando haya cuenta:

1. Formulario de tarjeta en `/plan` (Culqi.js con la llave pública).
2. El token que devuelve va a `BillingGateway::storeCard()`, que ya está escrito
   y guarda la referencia en `subscriptions.card_reference`.
3. Desde ese momento `billing:renew` cobra solo, cada madrugada.

También queda pendiente el **webhook** de Culqi, que hace falta para los medios
de pago asíncronos (Yape, PagoEfectivo) y para enterarse de una contracara. Con
tarjeta el cobro es síncrono y no hace falta.

## Cómo funciona mientras tanto

Con `BILLING_GATEWAY=manual`, que es lo que hay hoy:

- Cada cliente nuevo abre su **prueba gratuita** de 14 días al registrarse.
- `billing:renew` corre todas las madrugadas y **emite** el cobro de los
  periodos vencidos, con las sedes activas de cada cliente.
- No cobra nada: las facturas quedan **pendientes** y se concilian a mano
  cuando entra la transferencia.
- Nadie pasa a moroso por esto. Marcar moroso a quien pagó por otro medio es
  como se pierde un cliente que estaba al día.

## Dónde mirar

| Qué | Dónde |
|---|---|
| Qué pasarela se usa | `config/billing.php` |
| El contrato | `src/Platform/Domain/Contracts/BillingGateway.php` |
| Culqi | `src/Platform/Infrastructure/Billing/CulqiGateway.php` |
| Emitir y cobrar | `src/Platform/Application/Actions/{IssueInvoice,ChargeInvoice}.php` |
| El comando | `src/Platform/Presentation/Console/RenewSubscriptionsCommand.php` |
| Las pruebas | `tests/Integration/BillingTest.php` |

## Comprobantes electrónicos (SUNAT)

Aparte y todavía sin construir. El plan (§15.2) dice explícitamente que **no se
implementa SUNAT a mano**: se delega en un PSE. La columna
`invoices.document_url` está reservada para enlazar el comprobante emitido.
