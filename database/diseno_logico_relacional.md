# Diseño lógico y relacional de la base de datos — SIGEPF-JSVB

Fecha de extracción: 28 de septiembre de 2026.

## Alcance y evidencia

Este documento describe la estructura efectivamente instalada en `bd_js_ensenanza`, obtenida mediante `SHOW CREATE TABLE` e `information_schema`; no representa únicamente una propuesta.

- Servidor: **10.4.32-MariaDB**, conexión Laravel `mysql`.
- **41 tablas**, **286 columnas**, **40 claves foráneas**.
- 33 tablas de dominio y acceso; 8 tablas técnicas de Laravel.
- Exportación: `bd_js_ensenanza_estructura.sql`, sin datos, contraseñas ni tokens.
- No se modificaron tablas ni registros durante la extracción.
- No se verificó una importación en un servidor Oracle MySQL independiente.

## Objetivo

Organizar la identidad de las personas, sus cuentas y permisos, las participaciones de formación, las unidades médicas y las relaciones de supervisión, conservando adscripciones y documentos vinculados a cada participación.

## Organización del modelo

| Bloque | Tablas |
|---|---|
| Identidad y acceso | personas, users, roles, permisos, usuario_rol, rol_permiso |
| Ubicación y unidades | municipios, localidades, jurisdicciones, tipo_unidades, tipologias_unidades, unidades_medicas |
| Catálogos personales y académicos | generos, nacionalidades, tipos_personal, instituciones, carreras, nivel_academico |
| Formación y supervisión | asesores, personal_formacion, datos_academicos, adscripciones_asesores, adscripciones_personal, asigna_asesores |
| Seguimiento | categorias, tipos_incidencias, incidencias, tipos_consultas, productividad |
| Documentos y consulta | tipos_documentos, documentos_investigacion, observaciones_documentos, materiales_consultas |

## Criterios de diseño

- `personas` concentra identidad y contacto. `users` mantiene credenciales y correo de acceso.
- `users.id_persona` es opcional y único durante la transición de cuentas existentes. Una persona admite como máximo una cuenta.
- `users.name` aún existe por compatibilidad con las pantallas actuales; su retiro está pendiente.
- Los roles y permisos se relacionan mediante tablas intermedias con claves primarias compuestas.
- `personal_formacion` representa una participación; una persona puede tener varias.
- Un perfil de asesor corresponde a una única persona. Su existencia no concede automáticamente un rol.
- Las adscripciones conservan fechas de inicio y fin, separadas de la asignación asesor–participación.
- `datos_academicos.id_personal` es único: hay como máximo un registro académico por participación.
- La categoría de una incidencia se obtiene mediante su tipo; no se duplica en `incidencias`.
- Cada fila de `documentos_investigacion` representa un archivo/version. `id_documento_anterior` permite enlazar versiones y admite como máximo un sucesor por registro.
- Las observaciones apuntan al registro documental revisado y al usuario autor. El asesor es opcional para permitir revisores administrativos.
- Cada material está dirigido a una carrera y un tipo de personal en el esquema actual.
- Las claves foráneas existentes utilizan `ON DELETE RESTRICT`. Los índices únicos y primarios se detallan en el SQL.
- `deleted_at` habilita el almacenamiento de eliminación lógica donde existe. Su funcionamiento en consultas requiere modelos con SoftDeletes.
- Las claves únicas continúan aplicándose a registros eliminados lógicamente.

## Diagrama relacional general

Se muestran las claves primarias y foráneas. El diccionario posterior incluye todos los atributos. La cardinalidad se deriva de nulabilidad y unicidad reales: cero o uno, uno, o cero a muchos. Las tablas técnicas sin FK se omiten del dibujo.

```mermaid
erDiagram
    direction LR
    %% Esquema fisico completo de bd_js_ensenanza. Extraido el 2026-09-28.
    %% PK: primaria; FK: foranea; UK: unicidad individual.
    %% Varias PK en una tabla forman una PK compuesta.
    %% Comentarios de atributos: tipo SQL real, nulabilidad y autoincremento.
    %% Linea continua: FK forma parte de PK. Discontinua: relacion no identificadora.
    %% Solo se dibujan FK declaradas. sessions.user_id esta indexado pero NO tiene FK.
    adscripciones_asesores {
        bigint id_adscripcion PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_asesor FK "bigint(20) unsigned; NOT NULL"
        bigint id_unidad FK "bigint(20) unsigned; NOT NULL"
        date fecha_inicio "date; NOT NULL"
        date fecha_fin "date; NULL"
        text motivo_fin "text; NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    adscripciones_personal {
        bigint id_adscripcion PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_personal FK "bigint(20) unsigned; NOT NULL"
        bigint id_unidad FK "bigint(20) unsigned; NOT NULL"
        date fecha_inicio "date; NOT NULL"
        date fecha_fin "date; NULL"
        text motivo_fin "text; NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    asesores {
        bigint id_asesor PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_persona FK, UK "bigint(20) unsigned; NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    asigna_asesores {
        bigint id_asigna_asesor PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_asesor FK "bigint(20) unsigned; NOT NULL"
        bigint id_personal FK "bigint(20) unsigned; NOT NULL"
        date fecha_asignacion "date; NOT NULL"
        date fecha_fin "date; NULL"
        text motivo_fin "text; NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    cache {
        varchar key PK "varchar(255); NOT NULL"
        mediumtext value "mediumtext; NOT NULL"
        int expiration "int(11); NOT NULL"
    }
    cache_locks {
        varchar key PK "varchar(255); NOT NULL"
        varchar owner "varchar(255); NOT NULL"
        int expiration "int(11); NOT NULL"
    }
    carreras {
        bigint id_carrera PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nombre "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    categorias {
        bigint id_categoria PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nombre "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    datos_academicos {
        bigint id_dat_academico PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_personal FK, UK "bigint(20) unsigned; NOT NULL"
        bigint id_institucion FK "bigint(20) unsigned; NOT NULL"
        bigint id_carrera FK "bigint(20) unsigned; NOT NULL"
        bigint id_nivel_academico FK "bigint(20) unsigned; NOT NULL"
        varchar matricula "varchar(100); NOT NULL"
        decimal promedio "decimal(5,2); NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    documentos_investigacion {
        bigint id_documento PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_personal FK "bigint(20) unsigned; NOT NULL"
        bigint id_tipo_documento FK "bigint(20) unsigned; NOT NULL"
        bigint id_documento_anterior FK, UK "bigint(20) unsigned; NULL"
        varchar nombre_archivo "varchar(255); NOT NULL"
        varchar ruta_archivo "varchar(1024); NOT NULL"
        int version "int(10) unsigned; NOT NULL"
        varchar estado "varchar(50); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    failed_jobs {
        bigint id PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar uuid UK "varchar(255); NOT NULL"
        text connection "text; NOT NULL"
        text queue "text; NOT NULL"
        longtext payload "longtext; NOT NULL"
        longtext exception "longtext; NOT NULL"
        timestamp failed_at "timestamp; NOT NULL"
    }
    generos {
        bigint id_genero PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nombre "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    incidencias {
        bigint id_incidencia PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_personal FK "bigint(20) unsigned; NOT NULL"
        bigint id_tipo_incidencia FK "bigint(20) unsigned; NOT NULL"
        date fecha_hecho "date; NOT NULL"
        datetime fecha_registro "datetime; NOT NULL"
        varchar evidencia "varchar(1024); NULL"
        text descripcion "text; NOT NULL"
        varchar estado "varchar(50); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    instituciones {
        bigint id_institucion PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nombre "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    jobs {
        bigint id PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar queue "varchar(255); NOT NULL"
        longtext payload "longtext; NOT NULL"
        tinyint attempts "tinyint(3) unsigned; NOT NULL"
        int reserved_at "int(10) unsigned; NULL"
        int available_at "int(10) unsigned; NOT NULL"
        int created_at "int(10) unsigned; NOT NULL"
    }
    job_batches {
        varchar id PK "varchar(255); NOT NULL"
        varchar name "varchar(255); NOT NULL"
        int total_jobs "int(11); NOT NULL"
        int pending_jobs "int(11); NOT NULL"
        int failed_jobs "int(11); NOT NULL"
        longtext failed_job_ids "longtext; NOT NULL"
        mediumtext options "mediumtext; NULL"
        int cancelled_at "int(11); NULL"
        int created_at "int(11); NOT NULL"
        int finished_at "int(11); NULL"
    }
    jurisdicciones {
        bigint id_jurisdiccion PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nombre "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    localidades {
        bigint id_localidad PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_municipio FK "bigint(20) unsigned; NOT NULL"
        varchar nom_loc "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    materiales_consultas {
        bigint id_material PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_tipo_documento FK "bigint(20) unsigned; NOT NULL"
        bigint id_tipo_personal FK "bigint(20) unsigned; NOT NULL"
        bigint id_carrera FK "bigint(20) unsigned; NOT NULL"
        varchar titulo "varchar(255); NOT NULL"
        text descripcion "text; NULL"
        varchar nombre_archivo "varchar(255); NOT NULL"
        varchar ruta_archivo "varchar(1024); NOT NULL"
        datetime fecha_publicacion "datetime; NULL"
        varchar estado "varchar(50); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    migrations {
        int id PK "int(10) unsigned; NOT NULL; auto_increment"
        varchar migration "varchar(255); NOT NULL"
        int batch "int(11); NOT NULL"
    }
    municipios {
        bigint id_municipio PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nom_mun "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    nacionalidades {
        bigint id_nacionalidad PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nombre "varchar(255); NOT NULL"
        varchar codigo_iso "varchar(3); NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    nivel_academico {
        bigint id_nivel_academico PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nombre "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    observaciones_documentos {
        bigint id_revision_doc PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_documento FK "bigint(20) unsigned; NOT NULL"
        bigint user_id FK "bigint(20) unsigned; NOT NULL"
        bigint id_asesor FK "bigint(20) unsigned; NULL"
        text observacion "text; NOT NULL"
        datetime fecha_observacion "datetime; NOT NULL"
        varchar estado "varchar(50); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    password_reset_tokens {
        varchar email PK "varchar(255); NOT NULL"
        varchar token "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
    }
    permisos {
        bigint id PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar codigo UK "varchar(100); NOT NULL"
        varchar nombre "varchar(150); NOT NULL"
        text descripcion "text; NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
    }
    personal_formacion {
        bigint id_personal PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_persona FK "bigint(20) unsigned; NOT NULL"
        bigint id_tipo_personal FK "bigint(20) unsigned; NOT NULL"
        date fecha_ingreso "date; NULL"
        date fecha_termino "date; NULL"
        varchar estado "varchar(50); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    personas {
        bigint id_persona PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_localidad FK "bigint(20) unsigned; NULL"
        bigint id_genero FK "bigint(20) unsigned; NULL"
        bigint id_nacionalidad FK "bigint(20) unsigned; NULL"
        varchar nombre "varchar(255); NOT NULL"
        varchar ap "varchar(255); NOT NULL"
        varchar am "varchar(255); NULL"
        date fecha_nacimiento "date; NULL"
        varchar curp UK "varchar(18); NULL"
        varchar rfc "varchar(13); NULL"
        varchar desc_domicilio "varchar(255); NULL"
        varchar correo "varchar(255); NULL"
        varchar telefono "varchar(20); NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    productividad {
        bigint id_productividad PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_personal FK "bigint(20) unsigned; NOT NULL"
        bigint id_tipo_consulta FK "bigint(20) unsigned; NOT NULL"
        date fecha_actividad "date; NOT NULL"
        datetime fecha_registro "datetime; NOT NULL"
        varchar evidencia "varchar(1024); NULL"
        text descripcion "text; NULL"
        int num_actividades "int(10) unsigned; NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    roles {
        bigint id PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar codigo UK "varchar(50); NOT NULL"
        varchar nombre "varchar(100); NOT NULL"
        text descripcion "text; NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
    }
    rol_permiso {
        bigint rol_id PK, FK "bigint(20) unsigned; NOT NULL"
        bigint permiso_id PK, FK "bigint(20) unsigned; NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
    }
    sessions {
        varchar id PK "varchar(255); NOT NULL"
        bigint user_id "bigint(20) unsigned; NULL"
        varchar ip_address "varchar(45); NULL"
        text user_agent "text; NULL"
        longtext payload "longtext; NOT NULL"
        int last_activity "int(11); NOT NULL"
    }
    tipologias_unidades {
        bigint id_tipologia PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nombre "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    tipos_consultas {
        bigint id_tipo_consulta PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nombre "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    tipos_documentos {
        bigint id_tipo_documento PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nombre "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    tipos_incidencias {
        bigint id_tipo_incidencia PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_categoria FK "bigint(20) unsigned; NOT NULL"
        varchar nombre "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    tipos_personal {
        bigint id_tipo_personal PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nombre "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    tipo_unidades {
        bigint id_tipo_unidad PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar nombre "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    unidades_medicas {
        bigint id_unidad PK "bigint(20) unsigned; NOT NULL; auto_increment"
        bigint id_jurisdiccion FK "bigint(20) unsigned; NOT NULL"
        bigint id_localidad FK "bigint(20) unsigned; NOT NULL"
        bigint id_tipo_unidad FK "bigint(20) unsigned; NOT NULL"
        bigint id_tipologia FK "bigint(20) unsigned; NOT NULL"
        varchar clave_clues UK "varchar(20); NOT NULL"
        varchar nom_unidad "varchar(255); NOT NULL"
        varchar domicilio "varchar(255); NOT NULL"
        varchar referencias "varchar(255); NULL"
        char cp "char(5); NOT NULL"
        varchar horario "varchar(255); NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        timestamp deleted_at "timestamp; NULL"
    }
    users {
        bigint id PK "bigint(20) unsigned; NOT NULL; auto_increment"
        varchar name "varchar(255); NOT NULL"
        varchar email UK "varchar(255); NOT NULL"
        timestamp email_verified_at "timestamp; NULL"
        varchar password "varchar(255); NOT NULL"
        varchar remember_token "varchar(100); NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
        bigint id_persona FK, UK "bigint(20) unsigned; NULL"
    }
    usuario_rol {
        bigint user_id PK, FK "bigint(20) unsigned; NOT NULL"
        bigint rol_id PK, FK "bigint(20) unsigned; NOT NULL"
        timestamp created_at "timestamp; NULL"
        timestamp updated_at "timestamp; NULL"
    }
    asesores ||..o{ adscripciones_asesores : "id_asesor a id_asesor"
    unidades_medicas ||..o{ adscripciones_asesores : "id_unidad a id_unidad"
    personal_formacion ||..o{ adscripciones_personal : "id_personal a id_personal"
    unidades_medicas ||..o{ adscripciones_personal : "id_unidad a id_unidad"
    personas ||..o| asesores : "id_persona a id_persona"
    asesores ||..o{ asigna_asesores : "id_asesor a id_asesor"
    personal_formacion ||..o{ asigna_asesores : "id_personal a id_personal"
    carreras ||..o{ datos_academicos : "id_carrera a id_carrera"
    instituciones ||..o{ datos_academicos : "id_institucion a id_institucion"
    nivel_academico ||..o{ datos_academicos : "id_nivel_academico a id_nivel_academico"
    personal_formacion ||..o| datos_academicos : "id_personal a id_personal"
    documentos_investigacion |o..o| documentos_investigacion : "id_documento_anterior a id_documento"
    personal_formacion ||..o{ documentos_investigacion : "id_personal a id_personal"
    tipos_documentos ||..o{ documentos_investigacion : "id_tipo_documento a id_tipo_documento"
    personal_formacion ||..o{ incidencias : "id_personal a id_personal"
    tipos_incidencias ||..o{ incidencias : "id_tipo_incidencia a id_tipo_incidencia"
    municipios ||..o{ localidades : "id_municipio a id_municipio"
    carreras ||..o{ materiales_consultas : "id_carrera a id_carrera"
    tipos_documentos ||..o{ materiales_consultas : "id_tipo_documento a id_tipo_documento"
    tipos_personal ||..o{ materiales_consultas : "id_tipo_personal a id_tipo_personal"
    asesores |o..o{ observaciones_documentos : "id_asesor a id_asesor"
    documentos_investigacion ||..o{ observaciones_documentos : "id_documento a id_documento"
    users ||..o{ observaciones_documentos : "user_id a id"
    personas ||..o{ personal_formacion : "id_persona a id_persona"
    tipos_personal ||..o{ personal_formacion : "id_tipo_personal a id_tipo_personal"
    generos |o..o{ personas : "id_genero a id_genero"
    localidades |o..o{ personas : "id_localidad a id_localidad"
    nacionalidades |o..o{ personas : "id_nacionalidad a id_nacionalidad"
    personal_formacion ||..o{ productividad : "id_personal a id_personal"
    tipos_consultas ||..o{ productividad : "id_tipo_consulta a id_tipo_consulta"
    permisos ||--o{ rol_permiso : "permiso_id a id"
    roles ||--o{ rol_permiso : "rol_id a id"
    categorias ||..o{ tipos_incidencias : "id_categoria a id_categoria"
    jurisdicciones ||..o{ unidades_medicas : "id_jurisdiccion a id_jurisdiccion"
    localidades ||..o{ unidades_medicas : "id_localidad a id_localidad"
    tipologias_unidades ||..o{ unidades_medicas : "id_tipologia a id_tipologia"
    tipo_unidades ||..o{ unidades_medicas : "id_tipo_unidad a id_tipo_unidad"
    personas |o..o| users : "id_persona a id_persona"
    roles ||--o{ usuario_rol : "rol_id a id"
    users ||--o{ usuario_rol : "user_id a id"
```

## Relaciones y cardinalidades

| Tabla referenciada | Tabla dependiente | FK | Relación | Borrado |
|---|---|---|---|---|
| unidades_medicas.id_unidad | adscripciones_asesores | id_unidad | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| asesores.id_asesor | adscripciones_asesores | id_asesor | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| unidades_medicas.id_unidad | adscripciones_personal | id_unidad | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| personal_formacion.id_personal | adscripciones_personal | id_personal | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| personas.id_persona | asesores | id_persona | Padre → 0..1 hijos; hijo → 1 padre | RESTRICT |
| asesores.id_asesor | asigna_asesores | id_asesor | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| personal_formacion.id_personal | asigna_asesores | id_personal | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| personal_formacion.id_personal | datos_academicos | id_personal | Padre → 0..1 hijos; hijo → 1 padre | RESTRICT |
| nivel_academico.id_nivel_academico | datos_academicos | id_nivel_academico | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| instituciones.id_institucion | datos_academicos | id_institucion | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| carreras.id_carrera | datos_academicos | id_carrera | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| tipos_documentos.id_tipo_documento | documentos_investigacion | id_tipo_documento | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| personal_formacion.id_personal | documentos_investigacion | id_personal | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| documentos_investigacion.id_documento | documentos_investigacion | id_documento_anterior | Padre → 0..1 hijos; hijo → 0..1 padre | RESTRICT |
| tipos_incidencias.id_tipo_incidencia | incidencias | id_tipo_incidencia | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| personal_formacion.id_personal | incidencias | id_personal | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| municipios.id_municipio | localidades | id_municipio | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| tipos_personal.id_tipo_personal | materiales_consultas | id_tipo_personal | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| tipos_documentos.id_tipo_documento | materiales_consultas | id_tipo_documento | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| carreras.id_carrera | materiales_consultas | id_carrera | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| documentos_investigacion.id_documento | observaciones_documentos | id_documento | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| asesores.id_asesor | observaciones_documentos | id_asesor | Padre → 0..N hijos; hijo → 0..1 padre | RESTRICT |
| users.id | observaciones_documentos | user_id | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| tipos_personal.id_tipo_personal | personal_formacion | id_tipo_personal | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| personas.id_persona | personal_formacion | id_persona | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| localidades.id_localidad | personas | id_localidad | Padre → 0..N hijos; hijo → 0..1 padre | RESTRICT |
| generos.id_genero | personas | id_genero | Padre → 0..N hijos; hijo → 0..1 padre | RESTRICT |
| nacionalidades.id_nacionalidad | personas | id_nacionalidad | Padre → 0..N hijos; hijo → 0..1 padre | RESTRICT |
| tipos_consultas.id_tipo_consulta | productividad | id_tipo_consulta | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| personal_formacion.id_personal | productividad | id_personal | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| roles.id | rol_permiso | rol_id | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| permisos.id | rol_permiso | permiso_id | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| categorias.id_categoria | tipos_incidencias | id_categoria | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| tipologias_unidades.id_tipologia | unidades_medicas | id_tipologia | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| tipo_unidades.id_tipo_unidad | unidades_medicas | id_tipo_unidad | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| localidades.id_localidad | unidades_medicas | id_localidad | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| jurisdicciones.id_jurisdiccion | unidades_medicas | id_jurisdiccion | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| personas.id_persona | users | id_persona | Padre → 0..1 hijos; hijo → 0..1 padre | RESTRICT |
| users.id | usuario_rol | user_id | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |
| roles.id | usuario_rol | rol_id | Padre → 0..N hijos; hijo → 1 padre | RESTRICT |

## Diccionario de datos

PK: clave primaria. UNI: índice único de una columna. MUL: columna indexada sin unicidad individual. La definición completa de índices compuestos está en el archivo SQL. En las tablas intermedias, las columnas PK forman una clave conjunta.

### adscripciones_asesores

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_adscripcion | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_asesor | bigint(20) unsigned | No | MUL | asesores.id_asesor | — |
| id_unidad | bigint(20) unsigned | No | MUL | unidades_medicas.id_unidad | — |
| fecha_inicio | date | No | — | — | — |
| fecha_fin | date | Sí | — | — | — |
| motivo_fin | text | Sí | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### adscripciones_personal

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_adscripcion | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_personal | bigint(20) unsigned | No | MUL | personal_formacion.id_personal | — |
| id_unidad | bigint(20) unsigned | No | MUL | unidades_medicas.id_unidad | — |
| fecha_inicio | date | No | — | — | — |
| fecha_fin | date | Sí | — | — | — |
| motivo_fin | text | Sí | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### asesores

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_asesor | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_persona | bigint(20) unsigned | No | UNI | personas.id_persona | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### asigna_asesores

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_asigna_asesor | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_asesor | bigint(20) unsigned | No | MUL | asesores.id_asesor | — |
| id_personal | bigint(20) unsigned | No | MUL | personal_formacion.id_personal | — |
| fecha_asignacion | date | No | — | — | — |
| fecha_fin | date | Sí | — | — | — |
| motivo_fin | text | Sí | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### cache (tabla técnica de Laravel)

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| key | varchar(255) | No | PRI | — | — |
| value | mediumtext | No | — | — | — |
| expiration | int(11) | No | — | — | — |

### cache_locks (tabla técnica de Laravel)

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| key | varchar(255) | No | PRI | — | — |
| owner | varchar(255) | No | — | — | — |
| expiration | int(11) | No | — | — | — |

### carreras

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_carrera | bigint(20) unsigned | No | PRI | — | auto_increment |
| nombre | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### categorias

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_categoria | bigint(20) unsigned | No | PRI | — | auto_increment |
| nombre | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### datos_academicos

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_dat_academico | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_personal | bigint(20) unsigned | No | UNI | personal_formacion.id_personal | — |
| id_institucion | bigint(20) unsigned | No | MUL | instituciones.id_institucion | — |
| id_carrera | bigint(20) unsigned | No | MUL | carreras.id_carrera | — |
| id_nivel_academico | bigint(20) unsigned | No | MUL | nivel_academico.id_nivel_academico | — |
| matricula | varchar(100) | No | — | — | — |
| promedio | decimal(5,2) | Sí | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### documentos_investigacion

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_documento | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_personal | bigint(20) unsigned | No | MUL | personal_formacion.id_personal | — |
| id_tipo_documento | bigint(20) unsigned | No | MUL | tipos_documentos.id_tipo_documento | — |
| id_documento_anterior | bigint(20) unsigned | Sí | UNI | documentos_investigacion.id_documento | — |
| nombre_archivo | varchar(255) | No | — | — | — |
| ruta_archivo | varchar(1024) | No | — | — | — |
| version | int(10) unsigned | No | — | — | — |
| estado | varchar(50) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### failed_jobs (tabla técnica de Laravel)

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id | bigint(20) unsigned | No | PRI | — | auto_increment |
| uuid | varchar(255) | No | UNI | — | — |
| connection | text | No | — | — | — |
| queue | text | No | — | — | — |
| payload | longtext | No | — | — | — |
| exception | longtext | No | — | — | — |
| failed_at | timestamp | No | — | — | — |

### generos

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_genero | bigint(20) unsigned | No | PRI | — | auto_increment |
| nombre | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### incidencias

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_incidencia | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_personal | bigint(20) unsigned | No | MUL | personal_formacion.id_personal | — |
| id_tipo_incidencia | bigint(20) unsigned | No | MUL | tipos_incidencias.id_tipo_incidencia | — |
| fecha_hecho | date | No | — | — | — |
| fecha_registro | datetime | No | — | — | — |
| evidencia | varchar(1024) | Sí | — | — | — |
| descripcion | text | No | — | — | — |
| estado | varchar(50) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### instituciones

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_institucion | bigint(20) unsigned | No | PRI | — | auto_increment |
| nombre | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### job_batches (tabla técnica de Laravel)

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id | varchar(255) | No | PRI | — | — |
| name | varchar(255) | No | — | — | — |
| total_jobs | int(11) | No | — | — | — |
| pending_jobs | int(11) | No | — | — | — |
| failed_jobs | int(11) | No | — | — | — |
| failed_job_ids | longtext | No | — | — | — |
| options | mediumtext | Sí | — | — | — |
| cancelled_at | int(11) | Sí | — | — | — |
| created_at | int(11) | No | — | — | — |
| finished_at | int(11) | Sí | — | — | — |

### jobs (tabla técnica de Laravel)

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id | bigint(20) unsigned | No | PRI | — | auto_increment |
| queue | varchar(255) | No | MUL | — | — |
| payload | longtext | No | — | — | — |
| attempts | tinyint(3) unsigned | No | — | — | — |
| reserved_at | int(10) unsigned | Sí | — | — | — |
| available_at | int(10) unsigned | No | — | — | — |
| created_at | int(10) unsigned | No | — | — | — |

### jurisdicciones

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_jurisdiccion | bigint(20) unsigned | No | PRI | — | auto_increment |
| nombre | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### localidades

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_localidad | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_municipio | bigint(20) unsigned | No | MUL | municipios.id_municipio | — |
| nom_loc | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### materiales_consultas

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_material | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_tipo_documento | bigint(20) unsigned | No | MUL | tipos_documentos.id_tipo_documento | — |
| id_tipo_personal | bigint(20) unsigned | No | MUL | tipos_personal.id_tipo_personal | — |
| id_carrera | bigint(20) unsigned | No | MUL | carreras.id_carrera | — |
| titulo | varchar(255) | No | — | — | — |
| descripcion | text | Sí | — | — | — |
| nombre_archivo | varchar(255) | No | — | — | — |
| ruta_archivo | varchar(1024) | No | — | — | — |
| fecha_publicacion | datetime | Sí | — | — | — |
| estado | varchar(50) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### migrations (tabla técnica de Laravel)

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id | int(10) unsigned | No | PRI | — | auto_increment |
| migration | varchar(255) | No | — | — | — |
| batch | int(11) | No | — | — | — |

### municipios

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_municipio | bigint(20) unsigned | No | PRI | — | auto_increment |
| nom_mun | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### nacionalidades

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_nacionalidad | bigint(20) unsigned | No | PRI | — | auto_increment |
| nombre | varchar(255) | No | — | — | — |
| codigo_iso | varchar(3) | Sí | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### nivel_academico

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_nivel_academico | bigint(20) unsigned | No | PRI | — | auto_increment |
| nombre | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### observaciones_documentos

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_revision_doc | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_documento | bigint(20) unsigned | No | MUL | documentos_investigacion.id_documento | — |
| user_id | bigint(20) unsigned | No | MUL | users.id | — |
| id_asesor | bigint(20) unsigned | Sí | MUL | asesores.id_asesor | — |
| observacion | text | No | — | — | — |
| fecha_observacion | datetime | No | — | — | — |
| estado | varchar(50) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### password_reset_tokens (tabla técnica de Laravel)

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| email | varchar(255) | No | PRI | — | — |
| token | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |

### permisos

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id | bigint(20) unsigned | No | PRI | — | auto_increment |
| codigo | varchar(100) | No | UNI | — | — |
| nombre | varchar(150) | No | — | — | — |
| descripcion | text | Sí | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |

### personal_formacion

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_personal | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_persona | bigint(20) unsigned | No | MUL | personas.id_persona | — |
| id_tipo_personal | bigint(20) unsigned | No | MUL | tipos_personal.id_tipo_personal | — |
| fecha_ingreso | date | Sí | — | — | — |
| fecha_termino | date | Sí | — | — | — |
| estado | varchar(50) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### personas

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_persona | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_localidad | bigint(20) unsigned | Sí | MUL | localidades.id_localidad | — |
| id_genero | bigint(20) unsigned | Sí | MUL | generos.id_genero | — |
| id_nacionalidad | bigint(20) unsigned | Sí | MUL | nacionalidades.id_nacionalidad | — |
| nombre | varchar(255) | No | — | — | — |
| ap | varchar(255) | No | — | — | — |
| am | varchar(255) | Sí | — | — | — |
| fecha_nacimiento | date | Sí | — | — | — |
| curp | varchar(18) | Sí | UNI | — | — |
| rfc | varchar(13) | Sí | — | — | — |
| desc_domicilio | varchar(255) | Sí | — | — | — |
| correo | varchar(255) | Sí | — | — | — |
| telefono | varchar(20) | Sí | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### productividad

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_productividad | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_personal | bigint(20) unsigned | No | MUL | personal_formacion.id_personal | — |
| id_tipo_consulta | bigint(20) unsigned | No | MUL | tipos_consultas.id_tipo_consulta | — |
| fecha_actividad | date | No | — | — | — |
| fecha_registro | datetime | No | — | — | — |
| evidencia | varchar(1024) | Sí | — | — | — |
| descripcion | text | Sí | — | — | — |
| num_actividades | int(10) unsigned | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### rol_permiso

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| rol_id | bigint(20) unsigned | No | PRI | roles.id | — |
| permiso_id | bigint(20) unsigned | No | PRI | permisos.id | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |

### roles

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id | bigint(20) unsigned | No | PRI | — | auto_increment |
| codigo | varchar(50) | No | UNI | — | — |
| nombre | varchar(100) | No | — | — | — |
| descripcion | text | Sí | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |

### sessions (tabla técnica de Laravel)

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id | varchar(255) | No | PRI | — | — |
| user_id | bigint(20) unsigned | Sí | MUL | — | — |
| ip_address | varchar(45) | Sí | — | — | — |
| user_agent | text | Sí | — | — | — |
| payload | longtext | No | — | — | — |
| last_activity | int(11) | No | MUL | — | — |

### tipo_unidades

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_tipo_unidad | bigint(20) unsigned | No | PRI | — | auto_increment |
| nombre | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### tipologias_unidades

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_tipologia | bigint(20) unsigned | No | PRI | — | auto_increment |
| nombre | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### tipos_consultas

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_tipo_consulta | bigint(20) unsigned | No | PRI | — | auto_increment |
| nombre | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### tipos_documentos

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_tipo_documento | bigint(20) unsigned | No | PRI | — | auto_increment |
| nombre | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### tipos_incidencias

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_tipo_incidencia | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_categoria | bigint(20) unsigned | No | MUL | categorias.id_categoria | — |
| nombre | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### tipos_personal

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_tipo_personal | bigint(20) unsigned | No | PRI | — | auto_increment |
| nombre | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### unidades_medicas

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id_unidad | bigint(20) unsigned | No | PRI | — | auto_increment |
| id_jurisdiccion | bigint(20) unsigned | No | MUL | jurisdicciones.id_jurisdiccion | — |
| id_localidad | bigint(20) unsigned | No | MUL | localidades.id_localidad | — |
| id_tipo_unidad | bigint(20) unsigned | No | MUL | tipo_unidades.id_tipo_unidad | — |
| id_tipologia | bigint(20) unsigned | No | MUL | tipologias_unidades.id_tipologia | — |
| clave_clues | varchar(20) | No | UNI | — | — |
| nom_unidad | varchar(255) | No | — | — | — |
| domicilio | varchar(255) | No | — | — | — |
| referencias | varchar(255) | Sí | — | — | — |
| cp | char(5) | No | — | — | — |
| horario | varchar(255) | No | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| deleted_at | timestamp | Sí | — | — | — |

### users

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| id | bigint(20) unsigned | No | PRI | — | auto_increment |
| name | varchar(255) | No | — | — | — |
| email | varchar(255) | No | UNI | — | — |
| email_verified_at | timestamp | Sí | — | — | — |
| password | varchar(255) | No | — | — | — |
| remember_token | varchar(100) | Sí | — | — | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |
| id_persona | bigint(20) unsigned | Sí | UNI | personas.id_persona | — |

### usuario_rol

| Atributo | Tipo SQL real | Admite NULL | Clave/índice | Referencia | Extra |
|---|---|---|---|---|---|
| user_id | bigint(20) unsigned | No | PRI | users.id | — |
| rol_id | bigint(20) unsigned | No | PRI | roles.id | — |
| created_at | timestamp | Sí | — | — | — |
| updated_at | timestamp | Sí | — | — | — |

## Reglas todavía pendientes de implementación

La estructura no sustituye la validación y autorización de Laravel. Aún deben implementarse:
- Permisos por operación, alcance propio/asignado/global y protección de rutas.
- Validación de fechas y control de solapamientos de adscripciones y asignaciones.
- Estados admitidos y transiciones, sin equiparar término de periodo con liberación.
- Consistencia entre autor y perfil de asesor cuando ambos se indiquen.
- Consistencia de propietario/tipo entre versiones, secuencia de versión y prevención de ciclos.
- Validaciones de CURP, RFC, código postal, promedios y cantidades según las reglas acordadas.
- Almacenamiento privado, acceso autorizado e inmutabilidad de versiones.
- Bloqueo de eliminación lógica de catálogos utilizados; RESTRICT solo protege borrados físicos.
- Auditoría de seguridad e invitaciones: no existen tablas específicas para ellas en esta exportación.
- Requisitos y evidencias de liberación institucional: aún no modelados como flujo completo.

No se afirma una certificación de tercera forma normal: permanecen decisiones transitorias y restricciones semánticas que deben revisarse conforme se implementen los módulos.

## Uso del SQL como evidencia

1. Adjuntar el archivo SQL y este documento al reporte del proyecto.
2. Abrir `esquema_relacional.mmd` en un visor Mermaid para exportar el diagrama a SVG o PDF.
3. Para inspeccionarlo en phpMyAdmin, seleccionar una base vacía de prueba e importar el SQL; el archivo no selecciona ni crea una base automáticamente.
4. El archivo solo contiene estructura: no incluye registros de catálogos ni el historial de ejecución de Laravel. Para instalar la aplicación desde cero, usar sus migraciones; importar este SQL y luego ejecutar todas las migraciones produciría conflictos de tablas existentes.
5. El script desactiva las comprobaciones de FK durante la creación para resolver el orden de dependencias y restaura su valor al terminar.
