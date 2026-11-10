<?php

return [
    'entidades_federativas' => [
        'title' => 'Entidades federativas', 'group' => 'Geografía', 'key' => 'id_entidad_federativa', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255, 'unique' => true],
        ],
        'dependents' => [['municipios', 'id_entidad_federativa'], ['personas', 'id_entidad_federativa']],
    ],
    'instituciones_salud' => [
        'title' => 'Instituciones de salud', 'group' => 'Unidades médicas', 'key' => 'id_institucion_salud', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255, 'unique' => true],
        ],
        'dependents' => [['unidades_medicas', 'id_institucion_salud']],
    ],
    'programas' => [
        'title' => 'Programas', 'group' => 'Adscripción', 'key' => 'id_programa', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255, 'unique' => true],
            'clave_programa' => ['label' => 'Clave oficial del programa', 'required' => true, 'max' => 50, 'unique' => true],
        ],
        'dependents' => [['adscripciones_personal', 'id_programa']],
    ],
    'vertientes' => [
        'title' => 'Vertientes', 'group' => 'Adscripción', 'key' => 'id_vertiente', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255, 'unique' => true],
        ],
        'dependents' => [['adscripciones_personal', 'id_vertiente']],
    ],
    'tipos_campos_clinicos' => [
        'title' => 'Tipos de campo clínico', 'group' => 'Adscripción', 'key' => 'id_tipo_campo_clinico', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255, 'unique' => true],
        ],
        'dependents' => [['adscripciones_personal', 'id_tipo_campo_clinico']],
    ],
    'fuentes_financiamiento_beca' => [
        'title' => 'Fuentes de financiamiento de beca', 'group' => 'Adscripción', 'key' => 'id_fuente_financiamiento_beca', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255, 'unique' => true],
        ],
        'dependents' => [['adscripciones_personal', 'id_fuente_financiamiento_beca']],
    ],
    'municipios' => [
        'title' => 'Municipios', 'group' => 'Geografía', 'key' => 'id_municipio', 'label' => 'nom_mun',
        'fields' => [
            'nom_mun' => ['label' => 'Nombre del municipio', 'required' => true, 'max' => 255],
            'id_entidad_federativa' => ['label' => 'Entidad federativa', 'required' => false, 'reference' => 'entidades_federativas'],
        ],
        'dependents' => [['localidades', 'id_municipio']],
    ],
    'localidades' => [
        'title' => 'Localidades', 'group' => 'Geografía', 'key' => 'id_localidad', 'label' => 'nom_loc',
        'fields' => [
            'id_municipio' => ['label' => 'Municipio', 'required' => true, 'reference' => 'municipios'],
            'nom_loc' => ['label' => 'Nombre de la localidad', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['unidades_medicas', 'id_localidad'], ['personas', 'id_localidad']],
    ],
    'jurisdicciones' => [
        'title' => 'Jurisdicciones', 'group' => 'Unidades médicas', 'key' => 'id_jurisdiccion', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['unidades_medicas', 'id_jurisdiccion']],
    ],
    'tipo_unidades' => [
        'title' => 'Tipos de unidades', 'group' => 'Unidades médicas', 'key' => 'id_tipo_unidad', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['unidades_medicas', 'id_tipo_unidad']],
    ],
    'tipologias_unidades' => [
        'title' => 'Tipologías de unidades', 'group' => 'Unidades médicas', 'key' => 'id_tipologia', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['unidades_medicas', 'id_tipologia']],
    ],
    'unidades_medicas' => [
        'title' => 'Unidades médicas', 'group' => 'Unidades médicas', 'key' => 'id_unidad', 'label' => 'nom_unidad',
        'fields' => [
            'id_jurisdiccion' => ['label' => 'Jurisdicción', 'required' => true, 'reference' => 'jurisdicciones'],
            'id_localidad' => ['label' => 'Localidad', 'required' => true, 'reference' => 'localidades'],
            'id_tipo_unidad' => ['label' => 'Tipo de unidad', 'required' => true, 'reference' => 'tipo_unidades'],
            'id_tipologia' => ['label' => 'Tipología', 'required' => true, 'reference' => 'tipologias_unidades'],
            'clave_clues' => ['label' => 'Clave CLUES', 'required' => true, 'max' => 20, 'unique' => true],
            'id_institucion_salud' => ['label' => 'Institución de salud', 'required' => false, 'reference' => 'instituciones_salud'],
            'nom_unidad' => ['label' => 'Nombre de la unidad', 'required' => true, 'max' => 255],
            'domicilio' => ['label' => 'Domicilio', 'required' => true, 'max' => 255],
            'referencias' => ['label' => 'Referencias', 'required' => false, 'max' => 255],
            'cp' => ['label' => 'Código postal', 'required' => true, 'max' => 5, 'pattern' => '^[0-9]{5}$'],
            'horario' => ['label' => 'Horario', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['adscripciones_asesores', 'id_unidad'], ['adscripciones_personal', 'id_unidad']],
    ],
    'generos' => [
        'title' => 'Géneros', 'group' => 'Personas', 'key' => 'id_genero', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['personas', 'id_genero']],
    ],
    'nacionalidades' => [
        'title' => 'Nacionalidades', 'group' => 'Personas', 'key' => 'id_nacionalidad', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
            'codigo_iso' => ['label' => 'Código ISO', 'required' => false, 'max' => 3],
        ],
        'dependents' => [['personas', 'id_nacionalidad']],
    ],
    'tipos_personal' => [
        'title' => 'Tipos de personal', 'group' => 'Formación académica', 'key' => 'id_tipo_personal', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['personal_formacion', 'id_tipo_personal'], ['materiales_consultas', 'id_tipo_personal']],
    ],
    'instituciones' => [
        'title' => 'Instituciones', 'group' => 'Formación académica', 'key' => 'id_institucion', 'label' => 'nombre',
        'fields' => [
            'clave_institucion' => ['label' => 'Clave de institución en la constancia', 'required' => false, 'max' => 50, 'unique' => true],
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['datos_academicos', 'id_institucion']],
    ],
    'carreras' => [
        'title' => 'Carreras', 'group' => 'Formación académica', 'key' => 'id_carrera', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['datos_academicos', 'id_carrera'], ['materiales_consultas', 'id_carrera']],
    ],
    'nivel_academico' => [
        'title' => 'Niveles académicos', 'group' => 'Formación académica', 'key' => 'id_nivel_academico', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['datos_academicos', 'id_nivel_academico']],
    ],
    'categorias' => [
        'title' => 'Categorías de incidencias', 'group' => 'Seguimiento', 'key' => 'id_categoria', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['tipos_incidencias', 'id_categoria']],
    ],
    'tipos_incidencias' => [
        'title' => 'Tipos de incidencias', 'group' => 'Seguimiento', 'key' => 'id_tipo_incidencia', 'label' => 'nombre',
        'fields' => [
            'id_categoria' => ['label' => 'Categoría', 'required' => true, 'reference' => 'categorias'],
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['incidencias', 'id_tipo_incidencia']],
    ],
    'tipos_consultas' => [
        'title' => 'Tipos de consultas', 'group' => 'Seguimiento', 'key' => 'id_tipo_consulta', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['productividad', 'id_tipo_consulta']],
    ],
    'tipos_documentos' => [
        'title' => 'Tipos de documentos', 'group' => 'Seguimiento', 'key' => 'id_tipo_documento', 'label' => 'nombre',
        'fields' => [
            'nombre' => ['label' => 'Nombre', 'required' => true, 'max' => 255],
        ],
        'dependents' => [['documentos_investigacion', 'id_tipo_documento'], ['materiales_consultas', 'id_tipo_documento']],
    ],
];
