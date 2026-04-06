IMPORTANTE
==========
Por tamaño, este ZIP incluye la estructura y archivos clave, pero NO incluye `/vendor` ni el binario de Laravel.
Para ejecutarlo 100% directo:

Opción A (recomendada):
1) Crea un proyecto Laravel 10 vacío:
   composer create-project laravel/laravel contratos-firma
2) Copia el contenido de este ZIP encima del proyecto (sobrescribe `app/`, `routes/`, `resources/`, `database/`, etc.)
3) Sigue el README.

Opción B:
Usa este ZIP como base y ejecuta:
   composer install
pero necesitas que la estructura base de Laravel exista. Si falta algún archivo base, usa la Opción A.
