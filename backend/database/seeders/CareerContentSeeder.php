<?php

namespace Database\Seeders;

use App\Models\Career;
use Illuminate\Database\Seeder;

/**
 * Rellena la información académica "de ficha" de las carreras ya existentes:
 * título que otorga, modalidad, perfil profesional, campo laboral, requisitos
 * de ingreso, plan de estudios (materias por año) y horarios.
 *
 * IMPORTANTE
 *  - Es un seeder de EJEMPLO: los textos y materias son representativos y
 *    deben ser revisados/reemplazados por el administrador con la información
 *    oficial de la universidad.
 *  - Es NO destructivo e idempotente:
 *      · Solo rellena campos que están vacíos en `careers` (no sobrescribe
 *        datos ya cargados).
 *      · Solo inserta materias si la carrera aún no tiene ninguna.
 *      · Solo inserta horarios si la carrera aún no tiene ninguno.
 *    Ejecutar varias veces no duplica ni borra información existente.
 *
 *   php artisan db:seed --class=CareerContentSeeder
 */
class CareerContentSeeder extends Seeder
{
    public function run(): void
    {
        $requisitosBase = implode("\n", [
            'Título de bachiller en cualquiera de sus modalidades.',
            'Aprobar el examen de admisión.',
            'Aprobar el curso propedéutico de nivelación.',
            'Presentar la documentación de matrícula: partida de nacimiento, cédula de identidad y fotografías tamaño carné.',
        ]);

        // Modalidad por defecto (presencial) y excepciones semipresenciales.
        $modalidadPorCarrera = [
            'ADM-001' => 'Semipresencial',
            'CON-001' => 'Semipresencial',
            'ECO-001' => 'Semipresencial',
        ];

        $content = [
            'MED-001' => [
                'titulo_otorga' => 'Médico(a) y Cirujano(a)',
                'perfil_profesional' => 'El médico y cirujano formado en la UNCPGGL es un profesional con sólida base científica, humanista y ética, capacitado para la promoción de la salud, la prevención, el diagnóstico, el tratamiento y la rehabilitación de la enfermedad, así como para la investigación y la atención integral de la persona, la familia y la comunidad.',
                'campo_laboral' => 'Hospitales públicos y privados, centros de salud, clínicas especializadas, laboratorios clínicos, programas de salud pública, docencia e investigación, consulta médica privada y organismos de cooperación en salud.',
                'requisitos_ingreso' => $requisitosBase . "\nCertificado de salud y carné de vacunación actualizado.",
                'subjects' => [
                    1 => ['Anatomía Humana I', 'Histología', 'Biología Celular', 'Química General', 'Introducción a la Medicina'],
                    2 => ['Anatomía Humana II', 'Fisiología Humana', 'Bioquímica Médica', 'Microbiología I', 'Embriología'],
                    3 => ['Farmacología I', 'Microbiología II', 'Patología General', 'Semiología', 'Salud Pública'],
                    4 => ['Farmacología II', 'Patología Especial', 'Cirugía General', 'Medicina Interna', 'Epidemiología'],
                    5 => ['Pediatría', 'Ginecología y Obstetricia', 'Psiquiatría', 'Traumatología', 'Medicina Legal'],
                    6 => ['Internado Rotatorio', 'Rotación de Cirugía', 'Rotación de Medicina Interna', 'Trabajo de Grado'],
                ],
            ],
            'ENF-001' => [
                'titulo_otorga' => 'Licenciado(a) en Enfermería',
                'perfil_profesional' => 'Profesional de enfermería con formación científica, técnica y humanista, capaz de brindar cuidado integral a la persona, la familia y la comunidad en los niveles de promoción, prevención, curación y rehabilitación, con liderazgo, ética y compromiso social.',
                'campo_laboral' => 'Hospitales, centros y puestos de salud, clínicas, hogares de ancianos, programas de atención primaria, salud ocupacional, atención domiciliaria, docencia y organismos de cooperación.',
                'requisitos_ingreso' => $requisitosBase . "\nCertificado de salud y carné de vacunación actualizado.",
                'subjects' => [
                    1 => ['Anatomía y Fisiología Humana', 'Fundamentos de Enfermería', 'Biología General', 'Introducción a la Enfermería', 'Química General'],
                    2 => ['Enfermería Médico-Quirúrgica I', 'Farmacología', 'Microbiología y Parasitología', 'Nutrición y Dietoterapia', 'Psicología General'],
                    3 => ['Enfermería Médico-Quirúrgica II', 'Enfermería Materno-Infantil', 'Salud Pública', 'Ética y Legislación en Enfermería'],
                    4 => ['Enfermería Psiquiátrica', 'Administración de los Servicios de Enfermería', 'Investigación en Enfermería', 'Prácticas Clínicas'],
                ],
            ],
            'NUT-001' => [
                'titulo_otorga' => 'Licenciado(a) en Nutrición y Dietética',
                'perfil_profesional' => 'Profesional capacitado para evaluar, diagnosticar y tratar el estado nutricional de las personas, familias y comunidades, diseñando planes alimentarios, promoviendo hábitos de vida saludables y participando en la seguridad alimentaria y nutricional del país.',
                'campo_laboral' => 'Hospitales y clínicas, programas de nutrición y salud pública, comedores institucionales, industria de alimentos, consulta privada, nutrición deportiva y organismos de cooperación.',
                'requisitos_ingreso' => $requisitosBase . "\nCertificado de salud actualizado.",
                'subjects' => [
                    1 => ['Anatomía y Fisiología Humana', 'Química de los Alimentos', 'Fundamentos de Nutrición', 'Bioquímica', 'Matemática General'],
                    2 => ['Nutrición Humana I', 'Bromatología', 'Microbiología de los Alimentos', 'Fisiología Digestiva', 'Estadística'],
                    3 => ['Nutrición Humana II', 'Dietoterapia I', 'Nutrición Clínica', 'Evaluación del Estado Nutricional', 'Educación Alimentaria'],
                    4 => ['Dietoterapia II', 'Nutrición Comunitaria', 'Salud Pública', 'Administración de Servicios de Alimentación', 'Metodología de la Investigación'],
                ],
            ],
            'ICI-001' => [
                'titulo_otorga' => 'Ingeniero(a) Civil',
                'perfil_profesional' => 'Profesional con sólidos conocimientos en matemáticas, física y ciencias de la ingeniería, capacitado para planificar, diseñar, construir, supervisar y mantener obras de infraestructura civil, con criterios de seguridad, economía, sostenibilidad y responsabilidad social.',
                'campo_laboral' => 'Empresas constructoras, consultoras de diseño y supervisión, instituciones del Estado, proyectos de infraestructura vial, hidráulica y edificación, docencia e investigación.',
                'requisitos_ingreso' => $requisitosBase . "\nAptitud física compatible con el trabajo de campo.",
                'subjects' => [
                    1 => ['Cálculo Diferencial', 'Física General', 'Química General', 'Dibujo Técnico', 'Álgebra Lineal'],
                    2 => ['Cálculo Integral', 'Física II', 'Estática', 'Topografía I', 'Programación Aplicada'],
                    3 => ['Cálculo Vectorial', 'Dinámica', 'Resistencia de Materiales', 'Topografía II', 'Mecánica de Fluidos'],
                    4 => ['Análisis Estructural I', 'Mecánica de Suelos', 'Hidráulica', 'Concreto Reforzado I', 'Materiales de Construcción'],
                    5 => ['Análisis Estructural II', 'Puentes', 'Vías de Comunicación', 'Gestión de Proyectos', 'Trabajo de Grado'],
                ],
            ],
            'ARQ-001' => [
                'titulo_otorga' => 'Arquitecto(a)',
                'perfil_profesional' => 'Profesional capacitado para concebir, diseñar y dirigir proyectos arquitectónicos y urbanos que respondan a las necesidades humanas, el entorno y la sostenibilidad, integrando arte, técnica y compromiso social.',
                'campo_laboral' => 'Estudios de arquitectura, empresas constructoras, planificación urbana, diseño de interiores, restauración patrimonial, consultoría independiente, docencia e investigación.',
                'requisitos_ingreso' => $requisitosBase . "\nPortafolio o prueba de aptitud creativa (según convocatoria).",
                'subjects' => [
                    1 => ['Taller de Diseño Arquitectónico I', 'Geometría Descriptiva', 'Historia de la Arquitectura I', 'Dibujo Arquitectónico', 'Matemática General'],
                    2 => ['Taller de Diseño Arquitectónico II', 'Historia de la Arquitectura II', 'Materiales de Construcción', 'Topografía', 'Física Aplicada'],
                    3 => ['Taller de Diseño Arquitectónico III', 'Estructuras I', 'Instalaciones Sanitarias', 'Urbanismo I', 'Representación Digital'],
                    4 => ['Taller de Diseño Arquitectónico IV', 'Estructuras II', 'Instalaciones Eléctricas', 'Urbanismo II', 'Presupuestos'],
                    5 => ['Taller de Diseño Arquitectónico V', 'Diseño Urbano', 'Gestión de Proyectos', 'Patrimonio y Restauración', 'Trabajo de Grado'],
                ],
            ],
            'IIN-001' => [
                'titulo_otorga' => 'Ingeniero(a) Industrial',
                'perfil_profesional' => 'Profesional capaz de optimizar procesos productivos, de servicios y de gestión, integrando personas, materiales, información y tecnología para mejorar la productividad y la competitividad de las organizaciones con criterios de calidad y sostenibilidad.',
                'campo_laboral' => 'Industria manufacturera, empresas de servicios, logística y cadena de suministro, gestión de calidad, seguridad e higiene ocupacional, consultoría y docencia.',
                'requisitos_ingreso' => $requisitosBase . "\nAptitud física compatible con las prácticas en planta.",
                'subjects' => [
                    1 => ['Cálculo Diferencial', 'Física General', 'Química General', 'Introducción a la Ingeniería Industrial', 'Álgebra Lineal'],
                    2 => ['Cálculo Integral', 'Física II', 'Estadística I', 'Dibujo Asistido por Computadora', 'Contabilidad General'],
                    3 => ['Investigación de Operaciones I', 'Estadística II', 'Termodinámica', 'Costos Industriales', 'Procesos de Manufactura'],
                    4 => ['Investigación de Operaciones II', 'Gestión de la Calidad', 'Ingeniería de Métodos', 'Logística y Cadena de Suministro', 'Seguridad Industrial'],
                    5 => ['Planeación y Control de la Producción', 'Gestión de Proyectos', 'Ergonomía', 'Ingeniería Económica', 'Trabajo de Grado'],
                ],
            ],
            'ISI-001' => [
                'titulo_otorga' => 'Ingeniero(a) en Sistemas',
                'perfil_profesional' => 'Profesional capacitado para analizar, diseñar, desarrollar, implementar y gestionar sistemas informáticos y de información, con sólida formación en programación, bases de datos, redes y gestión tecnológica, actuando con ética y responsabilidad social.',
                'campo_laboral' => 'Empresas de desarrollo de software, departamentos de informática, administración de redes y servidores, consultoría tecnológica, banca, telecomunicaciones, docencia e investigación.',
                'requisitos_ingreso' => $requisitosBase,
                'subjects' => [
                    1 => ['Introducción a la Programación', 'Matemática Discreta', 'Matemática General', 'Introducción a los Sistemas', 'Lógica'],
                    2 => ['Programación Orientada a Objetos', 'Estructuras de Datos', 'Bases de Datos I', 'Arquitectura de Computadoras', 'Cálculo'],
                    3 => ['Bases de Datos II', 'Sistemas Operativos', 'Redes de Computadoras', 'Ingeniería de Software I', 'Estadística'],
                    4 => ['Ingeniería de Software II', 'Redes Avanzadas', 'Inteligencia Artificial', 'Seguridad Informática', 'Gestión de Proyectos'],
                    5 => ['Auditoría Informática', 'Desarrollo Web y Móvil', 'Gestión de Tecnologías de la Información', 'Emprendimiento Tecnológico', 'Trabajo de Grado'],
                ],
            ],
            'ITE-001' => [
                'titulo_otorga' => 'Ingeniero(a) en Telecomunicaciones',
                'perfil_profesional' => 'Profesional capacitado para diseñar, implementar y administrar sistemas de comunicaciones, redes de datos y servicios de telecomunicaciones, con dominio de la electrónica, el procesamiento de señales y las tecnologías de transmisión.',
                'campo_laboral' => 'Operadores de telecomunicaciones, proveedores de internet, radiodifusión, empresas de redes, mantenimiento de infraestructura, consultoría y docencia.',
                'requisitos_ingreso' => $requisitosBase,
                'subjects' => [
                    1 => ['Cálculo Diferencial', 'Física General', 'Circuitos Eléctricos I', 'Introducción a las Telecomunicaciones', 'Álgebra Lineal'],
                    2 => ['Cálculo Integral', 'Circuitos Eléctricos II', 'Electrónica Analógica', 'Señales y Sistemas', 'Programación'],
                    3 => ['Electrónica Digital', 'Campos Electromagnéticos', 'Sistemas de Comunicación', 'Redes de Datos', 'Probabilidad y Estadística'],
                    4 => ['Comunicaciones Digitales', 'Antenas y Propagación', 'Redes Inalámbricas', 'Fibra Óptica', 'Microprocesadores'],
                    5 => ['Redes Móviles', 'Comunicaciones Satelitales', 'Gestión de Redes', 'Regulación de Telecomunicaciones', 'Trabajo de Grado'],
                ],
            ],
            'FIS-001' => [
                'titulo_otorga' => 'Licenciado(a) en Física',
                'perfil_profesional' => 'Profesional con formación sólida en las leyes fundamentales de la naturaleza, capacitado para la investigación científica, la modelación de fenómenos físicos, la docencia y la aplicación de la física a la tecnología y la industria.',
                'campo_laboral' => 'Centros de investigación, universidades, laboratorios, industria tecnológica, sector energético, meteorología y docencia en educación media y superior.',
                'requisitos_ingreso' => $requisitosBase,
                'subjects' => [
                    1 => ['Cálculo Diferencial', 'Física General I', 'Álgebra Lineal', 'Química General', 'Introducción a la Física'],
                    2 => ['Cálculo Integral', 'Física General II', 'Geometría Analítica', 'Laboratorio de Física I', 'Programación Científica'],
                    3 => ['Cálculo Vectorial', 'Mecánica Clásica', 'Electromagnetismo I', 'Termodinámica', 'Ecuaciones Diferenciales'],
                    4 => ['Mecánica Cuántica', 'Electromagnetismo II', 'Óptica', 'Física Estadística', 'Metodología de la Investigación'],
                ],
            ],
            'AGR-001' => [
                'titulo_otorga' => 'Ingeniero(a) Agrónomo(a)',
                'perfil_profesional' => 'Profesional capacitado para gestionar de forma sostenible la producción agrícola, aplicando conocimientos de biología, suelos, fitotecnia y tecnología para mejorar la productividad y la seguridad alimentaria.',
                'campo_laboral' => 'Fincas y empresas agrícolas, cooperativas, instituciones del sector agropecuario, extensión rural, agroindustria, certificación de cultivos, investigación y docencia.',
                'requisitos_ingreso' => $requisitosBase . "\nAptitud física compatible con el trabajo de campo.",
                'subjects' => [
                    1 => ['Biología General', 'Química General', 'Matemática General', 'Introducción a la Agronomía', 'Botánica General'],
                    2 => ['Anatomía y Fisiología Vegetal', 'Edafología', 'Estadística', 'Bioquímica', 'Maquinaria Agrícola'],
                    3 => ['Fitotecnia I', 'Suelos y Fertilización', 'Entomología Agrícola', 'Riego y Drenaje', 'Genética'],
                    4 => ['Fitotecnia II', 'Fitopatología', 'Horticultura', 'Ganadería General', 'Extensión Rural'],
                    5 => ['Manejo Integrado de Cultivos', 'Agroecología', 'Formulación de Proyectos', 'Economía Agrícola', 'Trabajo de Grado'],
                ],
            ],
            'VET-001' => [
                'titulo_otorga' => 'Médico(a) Veterinario(a)',
                'perfil_profesional' => 'Profesional capacitado para prevenir, diagnosticar y tratar las enfermedades de los animales, promover la salud pública veterinaria, la producción pecuaria y el bienestar animal, con responsabilidad ética y social.',
                'campo_laboral' => 'Clínicas veterinarias, producción ganadera, salud pública e inocuidad de los alimentos, zoológicos, laboratorios, industria farmacéutica veterinaria, investigación y docencia.',
                'requisitos_ingreso' => $requisitosBase . "\nCertificado de salud y aptitud para el trabajo con animales.",
                'subjects' => [
                    1 => ['Anatomía Veterinaria I', 'Biología General', 'Química General', 'Introducción a la Medicina Veterinaria', 'Histología'],
                    2 => ['Anatomía Veterinaria II', 'Fisiología Animal', 'Bioquímica', 'Microbiología Veterinaria', 'Genética'],
                    3 => ['Farmacología Veterinaria', 'Patología General', 'Parasitología', 'Semiología Veterinaria', 'Nutrición Animal'],
                    4 => ['Patología Especial', 'Medicina Interna Veterinaria', 'Cirugía Veterinaria', 'Reproducción Animal', 'Salud Pública Veterinaria'],
                    5 => ['Clínica de Pequeños Animales', 'Clínica de Grandes Animales', 'Producción Animal', 'Legislación Veterinaria', 'Trabajo de Grado'],
                ],
            ],
            'FOR-001' => [
                'titulo_otorga' => 'Ingeniero(a) Forestal',
                'perfil_profesional' => 'Profesional capacitado para manejar, conservar y aprovechar de forma sostenible los recursos forestales, promoviendo la protección de los ecosistemas, la reforestación y el desarrollo rural.',
                'campo_laboral' => 'Instituciones forestales y ambientales, empresas madereras, áreas protegidas, proyectos de reforestación, certificación forestal, investigación y docencia.',
                'requisitos_ingreso' => $requisitosBase . "\nAptitud física compatible con el trabajo de campo.",
                'subjects' => [
                    1 => ['Biología General', 'Química General', 'Matemática General', 'Introducción a la Ingeniería Forestal', 'Botánica'],
                    2 => ['Dendrología', 'Suelos Forestales', 'Estadística', 'Ecología General', 'Topografía'],
                    3 => ['Silvicultura', 'Dasometría', 'Entomología Forestal', 'Hidrología', 'Genética Forestal'],
                    4 => ['Manejo de Bosques Naturales', 'Patología Forestal', 'Aprovechamiento Forestal', 'Viveros y Reforestación', 'Economía Forestal'],
                    5 => ['Planificación Forestal', 'Manejo de Cuencas', 'Industria Forestal', 'Formulación de Proyectos', 'Trabajo de Grado'],
                ],
            ],
            'ADM-001' => [
                'titulo_otorga' => 'Licenciado(a) en Administración de Empresas',
                'perfil_profesional' => 'Profesional capacitado para planificar, organizar, dirigir y controlar los recursos de una organización, con visión estratégica, liderazgo y ética, contribuyendo a la eficiencia y competitividad empresarial.',
                'campo_laboral' => 'Empresas públicas y privadas, banca y finanzas, gestión de talento humano, mercadeo, emprendimiento, consultoría, organismos no gubernamentales y docencia.',
                'requisitos_ingreso' => $requisitosBase,
                'subjects' => [
                    1 => ['Fundamentos de Administración', 'Matemática General', 'Contabilidad General', 'Introducción a la Economía', 'Comunicación Empresarial'],
                    2 => ['Contabilidad de Costos', 'Estadística', 'Microeconomía', 'Derecho Empresarial', 'Informática Aplicada'],
                    3 => ['Administración de Recursos Humanos', 'Macroeconomía', 'Mercadotecnia', 'Finanzas I', 'Investigación de Mercados'],
                    4 => ['Administración Estratégica', 'Finanzas II', 'Gestión de la Calidad', 'Emprendimiento', 'Metodología de la Investigación'],
                ],
            ],
            'CON-001' => [
                'titulo_otorga' => 'Licenciado(a) en Contabilidad Pública y Finanzas',
                'perfil_profesional' => 'Profesional capacitado para registrar, analizar y auditar la información financiera de las organizaciones, aplicar normas contables y fiscales, y contribuir a la toma de decisiones económicas con ética y responsabilidad.',
                'campo_laboral' => 'Firmas de auditoría y consultoría, departamentos de contabilidad y finanzas, banca, sector público, tributación, peritaje contable, docencia e investigación.',
                'requisitos_ingreso' => $requisitosBase,
                'subjects' => [
                    1 => ['Contabilidad General', 'Matemática Financiera', 'Fundamentos de Economía', 'Administración General', 'Informática Contable'],
                    2 => ['Contabilidad Intermedia', 'Costos I', 'Estadística', 'Derecho Mercantil', 'Microeconomía'],
                    3 => ['Contabilidad de Costos II', 'Finanzas I', 'Auditoría I', 'Tributación I', 'Macroeconomía'],
                    4 => ['Auditoría II', 'Finanzas II', 'Tributación II', 'Contabilidad Gubernamental', 'Metodología de la Investigación'],
                ],
            ],
            'ECO-001' => [
                'titulo_otorga' => 'Licenciado(a) en Economía',
                'perfil_profesional' => 'Profesional capacitado para analizar los fenómenos económicos y elaborar y evaluar políticas y proyectos, aplicando herramientas teóricas, matemáticas y estadísticas para contribuir al desarrollo económico y social.',
                'campo_laboral' => 'Bancos centrales y banca comercial, ministerios y entidades públicas, organismos internacionales, consultoría económica, investigación, docencia y sector privado.',
                'requisitos_ingreso' => $requisitosBase,
                'subjects' => [
                    1 => ['Introducción a la Economía', 'Matemática General', 'Estadística I', 'Historia Económica', 'Contabilidad General'],
                    2 => ['Microeconomía I', 'Macroeconomía I', 'Matemática Financiera', 'Estadística II', 'Cálculo'],
                    3 => ['Microeconomía II', 'Macroeconomía II', 'Econometría I', 'Economía del Desarrollo', 'Finanzas Públicas'],
                    4 => ['Econometría II', 'Política Económica', 'Comercio Internacional', 'Formulación y Evaluación de Proyectos', 'Metodología de la Investigación'],
                ],
            ],
        ];

        $blocks = [
            ['07:00', '09:00'],
            ['09:00', '11:00'],
            ['10:00', '12:00'],
            ['13:00', '15:00'],
            ['15:00', '17:00'],
        ];

        $days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

        $rooms = [
            'Aula 101', 'Aula 102', 'Aula 201', 'Aula 202', 'Aula 301',
            'Aula 302', 'Aula 401', 'Lab. Ciencias', 'Lab. Informática',
        ];

        $teachers = [
            'Dr. Roberto Chavarría', 'Dra. Carmen López', 'Ing. Javier Obando',
            'Lic. Sandra Potosme', 'Ing. Luis Centeno', 'Dra. Rosa Espinoza',
            'Lic. Ernesto Báez', 'Ing. Patricia Vega', 'Arq. Manuel Sánchez',
            'Dr. Pedro Gutiérrez', 'Lic. Ana Rodríguez', 'Ing. Carlos Mendoza',
        ];

        foreach ($content as $code => $data) {
            $career = Career::where('code', $code)->first();
            if (! $career) {
                continue;
            }

            // 1) Campos de ficha: solo se rellenan si están vacíos.
            $fill = [];
            foreach (['titulo_otorga', 'perfil_profesional', 'campo_laboral', 'requisitos_ingreso'] as $field) {
                if (! isset($data[$field])) {
                    continue;
                }
                $current = $career->getAttribute($field);
                if ($current === null || trim((string) $current) === '') {
                    $fill[$field] = $data[$field];
                }
            }

            // Modalidad: valor por defecto o excepción semipresencial.
            if ($career->modalidad === null || trim((string) $career->modalidad) === '') {
                $fill['modalidad'] = $modalidadPorCarrera[$code] ?? 'Presencial';
            }

            if (! empty($fill)) {
                $career->fill($fill)->save();
            }

            // 2) Plan de estudios: solo si la carrera aún no tiene materias.
            if ($career->subjects()->count() === 0) {
                $rows = [];
                foreach ($data['subjects'] as $year => $names) {
                    $half = (int) ceil(count($names) / 2);
                    foreach (array_values($names) as $index => $name) {
                        $rows[] = [
                            'career_id' => $career->id,
                            'year' => $year,
                            'period' => $index < $half ? 'I Semestre' : 'II Semestre',
                            'name' => $name,
                            'credits' => 4,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (! empty($rows)) {
                    $career->subjects()->insert($rows);
                }
            }

            // 3) Horarios: solo si la carrera aún no tiene ninguno.
            if ($career->schedules()->count() === 0) {
                $firstYear = $career->subjects()
                    ->where('year', 1)
                    ->orderBy('id')
                    ->pluck('name')
                    ->take(5)
                    ->values();

                $rows = [];
                foreach ($firstYear as $index => $subject) {
                    [$start, $end] = $blocks[$index % count($blocks)];
                    $rows[] = [
                        'career_id' => $career->id,
                        'subject_name' => $subject,
                        'teacher_name' => $teachers[$index % count($teachers)],
                        'day_of_week' => $days[$index % count($days)],
                        'start_time' => $start,
                        'end_time' => $end,
                        'classroom' => $rooms[$index % count($rooms)],
                        'year_of_study' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (! empty($rows)) {
                    $career->schedules()->insert($rows);
                }
            }
        }
    }
}
