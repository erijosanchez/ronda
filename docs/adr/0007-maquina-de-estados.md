# 0007. Máquina de estados declarativa

**Estado:** aceptado · **Fecha:** 2026-09-03

## Contexto
Los cinco módulos de reports-trimax modelaban su flujo con una columna de texto y condicionales repartidos. Nada impedía que un registro terminara en un estado imposible.

## Decisión
`spatie/laravel-model-states`: estados como clases, transiciones declaradas, y excepción al intentar una inválida.

## Alternativas descartadas
- **`if` sobre una columna `estado`:** cada nuevo estado obliga a auditar todos los condicionales existentes.

## Consecuencias que se aceptan
Curva de aprendizaje y más archivos. A cambio, un estado inválido deja de ser posible en vez de ser improbable.
