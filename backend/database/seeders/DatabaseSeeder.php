<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('users')->exists()) {
            return;
        }

        $careers = [
            ['name' => 'Medicina y Cirugía',              'code' => 'MED-001', 'faculty' => 'Ciencias de la Salud',                  'duration_years' => 6, 'enrollment_fee' => 800.00, 'monthly_fee' => 500.00, 'description' => 'Formación de médicos generales.', 'is_active' => true],
            ['name' => 'Enfermería',                       'code' => 'ENF-001', 'faculty' => 'Ciencias de la Salud',                  'duration_years' => 4, 'enrollment_fee' => 600.00, 'monthly_fee' => 350.00, 'description' => 'Cuidado integral de la salud.', 'is_active' => true],
            ['name' => 'Nutrición y Dietética',            'code' => 'NUT-001', 'faculty' => 'Ciencias de la Salud',                  'duration_years' => 4, 'enrollment_fee' => 600.00, 'monthly_fee' => 320.00, 'description' => 'Alimentación saludable y prevención.', 'is_active' => true],
            ['name' => 'Ingeniería Civil',                 'code' => 'ICI-001', 'faculty' => 'Arquitectura e Ingeniería',             'duration_years' => 5, 'enrollment_fee' => 700.00, 'monthly_fee' => 420.00, 'description' => 'Diseño y construcción de infraestructuras.', 'is_active' => true],
            ['name' => 'Arquitectura',                     'code' => 'ARQ-001', 'faculty' => 'Arquitectura e Ingeniería',             'duration_years' => 5, 'enrollment_fee' => 700.00, 'monthly_fee' => 420.00, 'description' => 'Diseño de espacios habitables.', 'is_active' => true],
            ['name' => 'Ingeniería Industrial',            'code' => 'IIN-001', 'faculty' => 'Arquitectura e Ingeniería',             'duration_years' => 5, 'enrollment_fee' => 700.00, 'monthly_fee' => 400.00, 'description' => 'Optimización de procesos productivos.', 'is_active' => true],
            ['name' => 'Ingeniería en Sistemas',           'code' => 'ISI-001', 'faculty' => 'Ciencias y Tecnología',                 'duration_years' => 5, 'enrollment_fee' => 700.00, 'monthly_fee' => 400.00, 'description' => 'Desarrollo de software y sistemas.', 'is_active' => true],
            ['name' => 'Ingeniería en Telecomunicaciones', 'code' => 'ITE-001', 'faculty' => 'Ciencias y Tecnología',                 'duration_years' => 5, 'enrollment_fee' => 700.00, 'monthly_fee' => 400.00, 'description' => 'Redes y comunicaciones digitales.', 'is_active' => true],
            ['name' => 'Física',                           'code' => 'FIS-001', 'faculty' => 'Ciencias y Tecnología',                 'duration_years' => 4, 'enrollment_fee' => 500.00, 'monthly_fee' => 280.00, 'description' => 'Ciencias exactas e investigación.', 'is_active' => true],
            ['name' => 'Ingeniería Agronómica',            'code' => 'AGR-001', 'faculty' => 'Ciencias Agropecuarias',                'duration_years' => 5, 'enrollment_fee' => 600.00, 'monthly_fee' => 350.00, 'description' => 'Producción agrícola sostenible.', 'is_active' => true],
            ['name' => 'Medicina Veterinaria',             'code' => 'VET-001', 'faculty' => 'Ciencias Agropecuarias',                'duration_years' => 5, 'enrollment_fee' => 700.00, 'monthly_fee' => 400.00, 'description' => 'Salud y bienestar animal.', 'is_active' => true],
            ['name' => 'Ingeniería Forestal',              'code' => 'FOR-001', 'faculty' => 'Ciencias Agropecuarias',                'duration_years' => 5, 'enrollment_fee' => 600.00, 'monthly_fee' => 350.00, 'description' => 'Gestión sostenible de bosques.', 'is_active' => true],
            ['name' => 'Administración de Empresas',       'code' => 'ADM-001', 'faculty' => 'Ciencias Económicas y Administrativas', 'duration_years' => 4, 'enrollment_fee' => 600.00, 'monthly_fee' => 350.00, 'description' => 'Gestión estratégica de organizaciones.', 'is_active' => true],
            ['name' => 'Contabilidad Pública y Finanzas',  'code' => 'CON-001', 'faculty' => 'Ciencias Económicas y Administrativas', 'duration_years' => 4, 'enrollment_fee' => 600.00, 'monthly_fee' => 340.00, 'description' => 'Gestión financiera y contable.', 'is_active' => true],
            ['name' => 'Economía',                         'code' => 'ECO-001', 'faculty' => 'Ciencias Económicas y Administrativas', 'duration_years' => 4, 'enrollment_fee' => 600.00, 'monthly_fee' => 340.00, 'description' => 'Análisis económico para el desarrollo.', 'is_active' => true],
        ];

        foreach ($careers as &$c) { $c['created_at'] = now(); $c['updated_at'] = now(); }
        DB::table('careers')->insert($careers);

        $careerIds = DB::table('careers')->pluck('id', 'code');

        $userData = [
            ['name' => 'María José Téllez',    'email' => 'maria@uncpggl.edu.ni',   'career_code' => 'MED-001', 'student_code' => '2024-MED-001', 'year' => 2],
            ['name' => 'Carlos Mendoza',        'email' => 'carlos@uncpggl.edu.ni',  'career_code' => 'ISI-001', 'student_code' => '2023-ISI-001', 'year' => 3],
            ['name' => 'Lucía Hernández',       'email' => 'lucia@uncpggl.edu.ni',   'career_code' => 'ADM-001', 'student_code' => '2024-ADM-001', 'year' => 1],
            ['name' => 'José Antonio Ruiz',     'email' => 'jose@uncpggl.edu.ni',    'career_code' => 'ICI-001', 'student_code' => '2022-ICI-001', 'year' => 4],
            ['name' => 'Ana Sofía Morales',     'email' => 'ana@uncpggl.edu.ni',     'career_code' => 'ENF-001', 'student_code' => '2025-ENF-001', 'year' => 1],
            ['name' => 'Pedro Gutiérrez',       'email' => 'pedro@uncpggl.edu.ni',   'career_code' => 'MED-001', 'student_code' => '2023-MED-002', 'year' => 3],
            ['name' => 'Gabriela Castillo',     'email' => 'gabriela@uncpggl.edu.ni','career_code' => 'CON-001', 'student_code' => '2024-CON-001', 'year' => 2],
            ['name' => 'Diego Ramírez',         'email' => 'diego@uncpggl.edu.ni',   'career_code' => 'ISI-001', 'student_code' => '2025-ISI-002', 'year' => 1],
            ['name' => 'Valeria Ortega',        'email' => 'valeria@uncpggl.edu.ni', 'career_code' => 'ARQ-001', 'student_code' => '2022-ARQ-001', 'year' => 4],
            ['name' => 'Marcos Espinoza',       'email' => 'marcos@uncpggl.edu.ni',  'career_code' => 'AGR-001', 'student_code' => '2023-AGR-001', 'year' => 3], 
             
];
        

        foreach ($userData as $u) {
            $userId = DB::table('users')->insertGetId([
                'name' => $u['name'], 'email' => $u['email'],
                'password' => Hash::make('password123'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('students')->insert([
                'user_id' => $userId, 'career_id' => $careerIds[$u['career_code']],
                'student_code' => $u['student_code'], 'year_of_study' => $u['year'],
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $userIds = DB::table('users')->pluck('id', 'email');

        $schedules = [
            ['career_id' => $careerIds['MED-001'], 'subject_name' => 'Anatomía I',            'teacher_name' => 'Dr. Roberto Chavarría', 'day_of_week' => 'Lunes',     'start_time' => '07:00', 'end_time' => '09:00', 'classroom' => 'Aula 201',        'year_of_study' => 1],
            ['career_id' => $careerIds['MED-001'], 'subject_name' => 'Química General',       'teacher_name' => 'Dra. Carmen López',     'day_of_week' => 'Martes',    'start_time' => '07:00', 'end_time' => '09:00', 'classroom' => 'Lab. Ciencias',   'year_of_study' => 1],
            ['career_id' => $careerIds['MED-001'], 'subject_name' => 'Anatomía II',           'teacher_name' => 'Dr. Roberto Chavarría', 'day_of_week' => 'Miércoles', 'start_time' => '07:00', 'end_time' => '09:00', 'classroom' => 'Aula 201',        'year_of_study' => 2],
            ['career_id' => $careerIds['MED-001'], 'subject_name' => 'Fisiología Humana',     'teacher_name' => 'Dra. Carmen López',     'day_of_week' => 'Martes',    'start_time' => '09:00', 'end_time' => '11:00', 'classroom' => 'Aula 202',        'year_of_study' => 2],
            ['career_id' => $careerIds['MED-001'], 'subject_name' => 'Bioquímica Clínica',    'teacher_name' => 'Dr. Manuel Sánchez',    'day_of_week' => 'Miércoles', 'start_time' => '09:00', 'end_time' => '11:00', 'classroom' => 'Lab. Ciencias',   'year_of_study' => 2],

            ['career_id' => $careerIds['ISI-001'], 'subject_name' => 'Programación I',        'teacher_name' => 'Ing. Javier Obando',    'day_of_week' => 'Lunes',     'start_time' => '08:00', 'end_time' => '10:00', 'classroom' => 'Lab. Informática','year_of_study' => 1],
            ['career_id' => $careerIds['ISI-001'], 'subject_name' => 'Matemática Discreta',   'teacher_name' => 'Lic. Ernesto Báez',     'day_of_week' => 'Miércoles', 'start_time' => '08:00', 'end_time' => '10:00', 'classroom' => 'Aula 301',        'year_of_study' => 1],
            ['career_id' => $careerIds['ISI-001'], 'subject_name' => 'Base de Datos I',       'teacher_name' => 'Ing. Luis Centeno',     'day_of_week' => 'Viernes',   'start_time' => '08:00', 'end_time' => '10:00', 'classroom' => 'Lab. Informática','year_of_study' => 2],
            ['career_id' => $careerIds['ISI-001'], 'subject_name' => 'Programación II',       'teacher_name' => 'Ing. Javier Obando',    'day_of_week' => 'Martes',    'start_time' => '10:00', 'end_time' => '12:00', 'classroom' => 'Lab. Informática','year_of_study' => 2],
            ['career_id' => $careerIds['ISI-001'], 'subject_name' => 'Base de Datos II',      'teacher_name' => 'Ing. Luis Centeno',     'day_of_week' => 'Lunes',     'start_time' => '10:00', 'end_time' => '12:00', 'classroom' => 'Lab. Informática','year_of_study' => 3],
            ['career_id' => $careerIds['ISI-001'], 'subject_name' => 'Redes de Computadoras', 'teacher_name' => 'Ing. Patricia Vega',    'day_of_week' => 'Miércoles', 'start_time' => '10:00', 'end_time' => '12:00', 'classroom' => 'Aula 301',        'year_of_study' => 3],
            ['career_id' => $careerIds['ISI-001'], 'subject_name' => 'Ingeniería de Software','teacher_name'=> 'Ing. Javier Obando',    'day_of_week' => 'Viernes',   'start_time' => '10:00', 'end_time' => '12:00', 'classroom' => 'Lab. Informática','year_of_study' => 3],
            ['career_id' => $careerIds['ISI-001'], 'subject_name' => 'Inteligencia Artificial','teacher_name'=> 'Ing. Patricia Vega',    'day_of_week' => 'Jueves',    'start_time' => '08:00', 'end_time' => '10:00', 'classroom' => 'Aula 301',        'year_of_study' => 4],
            ['career_id' => $careerIds['ISI-001'], 'subject_name' => 'Proyecto de Grado',     'teacher_name' => 'Ing. Luis Centeno',     'day_of_week' => 'Viernes',   'start_time' => '08:00', 'end_time' => '10:00', 'classroom' => 'Lab. Informática','year_of_study' => 4],

            ['career_id' => $careerIds['ADM-001'], 'subject_name' => 'Fund. de Administración','teacher_name'=> 'Lic. Sandra Potosme', 'day_of_week' => 'Lunes',     'start_time' => '13:00', 'end_time' => '15:00', 'classroom' => 'Aula 101',        'year_of_study' => 1],
            ['career_id' => $careerIds['ADM-001'], 'subject_name' => 'Matemática General',    'teacher_name' => 'Lic. Ernesto Báez',    'day_of_week' => 'Martes',    'start_time' => '07:00', 'end_time' => '09:00', 'classroom' => 'Aula 102',        'year_of_study' => 1],

            ['career_id' => $careerIds['ICI-001'], 'subject_name' => 'Mecánica de Suelos',    'teacher_name' => 'Ing. Pedro Gutiérrez', 'day_of_week' => 'Jueves',    'start_time' => '07:00', 'end_time' => '09:00', 'classroom' => 'Aula 401',        'year_of_study' => 4],
            ['career_id' => $careerIds['ICI-001'], 'subject_name' => 'Estructuras I',         'teacher_name' => 'Ing. Pedro Gutiérrez', 'day_of_week' => 'Viernes',   'start_time' => '09:00', 'end_time' => '11:00', 'classroom' => 'Aula 401',        'year_of_study' => 4],
        ];
        foreach ($schedules as &$s) { $s['created_at'] = now(); $s['updated_at'] = now(); }
        DB::table('schedules')->insert($schedules);

        $exams = [
            ['career_id' => $careerIds['MED-001'], 'subject_name' => 'Anatomía I',            'exam_type' => 'Primer Parcial',  'scheduled_at' => '2026-06-10 08:00:00', 'classroom' => 'Aula 201',        'year_of_study' => 1],
            ['career_id' => $careerIds['MED-001'], 'subject_name' => 'Anatomía II',           'exam_type' => 'Segundo Parcial', 'scheduled_at' => '2026-06-20 08:00:00', 'classroom' => 'Aula 201',        'year_of_study' => 2],
            ['career_id' => $careerIds['MED-001'], 'subject_name' => 'Fisiología Humana',     'exam_type' => 'Examen Final',    'scheduled_at' => '2026-07-05 09:00:00', 'classroom' => 'Aula 202',        'year_of_study' => 2],

            ['career_id' => $careerIds['ISI-001'], 'subject_name' => 'Programación I',        'exam_type' => 'Primer Parcial',  'scheduled_at' => '2026-06-12 08:00:00', 'classroom' => 'Lab. Informática','year_of_study' => 1],
            ['career_id' => $careerIds['ISI-001'], 'subject_name' => 'Base de Datos II',      'exam_type' => 'Segundo Parcial', 'scheduled_at' => '2026-06-18 10:00:00', 'classroom' => 'Lab. Informática','year_of_study' => 3],
            ['career_id' => $careerIds['ISI-001'], 'subject_name' => 'Redes de Computadoras', 'exam_type' => 'Examen Final',    'scheduled_at' => '2026-07-08 08:00:00', 'classroom' => 'Aula 301',        'year_of_study' => 3],

            ['career_id' => $careerIds['ADM-001'], 'subject_name' => 'Matemática General',    'exam_type' => 'Primer Parcial',  'scheduled_at' => '2026-06-15 07:00:00', 'classroom' => 'Aula 102',        'year_of_study' => 1],
            ['career_id' => $careerIds['ICI-001'], 'subject_name' => 'Estructuras I',         'exam_type' => 'Examen Final',    'scheduled_at' => '2026-07-10 07:00:00', 'classroom' => 'Aula 401',        'year_of_study' => 4],
        ];
        foreach ($exams as &$e) { $e['created_at'] = now(); $e['updated_at'] = now(); }
        DB::table('exams')->insert($exams);

      $grades = [
    
    ['user_id' => $userIds['maria@uncpggl.edu.ni'],    'career_id' => $careerIds['MED-001'], 'subject_name' => 'Anatomía I',            'first_partial' => 85, 'second_partial' => 78, 'final_exam' => 82, 'final_grade' => 82, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['maria@uncpggl.edu.ni'],    'career_id' => $careerIds['MED-001'], 'subject_name' => 'Bioquímica I',          'first_partial' => 72, 'second_partial' => 68, 'final_exam' => 70, 'final_grade' => 70, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['maria@uncpggl.edu.ni'],    'career_id' => $careerIds['MED-001'], 'subject_name' => 'Histología',            'first_partial' => 55, 'second_partial' => 58, 'final_exam' => 50, 'final_grade' => 54, 'status' => 'failed',   'period' => 'I-2026'],
    ['user_id' => $userIds['carlos@uncpggl.edu.ni'],   'career_id' => $careerIds['ISI-001'], 'subject_name' => 'Programación III',      'first_partial' => 90, 'second_partial' => 88, 'final_exam' => 92, 'final_grade' => 90, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['carlos@uncpggl.edu.ni'],   'career_id' => $careerIds['ISI-001'], 'subject_name' => 'Base de Datos I',       'first_partial' => 85, 'second_partial' => 82, 'final_exam' => 88, 'final_grade' => 85, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['carlos@uncpggl.edu.ni'],   'career_id' => $careerIds['ISI-001'], 'subject_name' => 'Sistemas Operativos',   'first_partial' => 60, 'second_partial' => 65, 'final_exam' => 58, 'final_grade' => 61, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['lucia@uncpggl.edu.ni'],    'career_id' => $careerIds['ADM-001'], 'subject_name' => 'Fund. Administración',  'first_partial' => 88, 'second_partial' => null, 'final_exam' => null, 'final_grade' => null, 'status' => 'pending', 'period' => 'I-2026'],
    ['user_id' => $userIds['lucia@uncpggl.edu.ni'],    'career_id' => $careerIds['ADM-001'], 'subject_name' => 'Matemática General',    'first_partial' => 75, 'second_partial' => null, 'final_exam' => null, 'final_grade' => null, 'status' => 'pending', 'period' => 'I-2026'],
    ['user_id' => $userIds['jose@uncpggl.edu.ni'],     'career_id' => $careerIds['ICI-001'], 'subject_name' => 'Mecánica de Suelos',    'first_partial' => 78, 'second_partial' => 80, 'final_exam' => 76, 'final_grade' => 78, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['jose@uncpggl.edu.ni'],     'career_id' => $careerIds['ICI-001'], 'subject_name' => 'Estructuras I',         'first_partial' => 45, 'second_partial' => 52, 'final_exam' => 48, 'final_grade' => 48, 'status' => 'failed',   'period' => 'I-2026'],
    ['user_id' => $userIds['jose@uncpggl.edu.ni'],     'career_id' => $careerIds['ICI-001'], 'subject_name' => 'Topografía II',         'first_partial' => 80, 'second_partial' => 77, 'final_exam' => 82, 'final_grade' => 80, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['ana@uncpggl.edu.ni'],      'career_id' => $careerIds['ENF-001'], 'subject_name' => 'Anatomía Básica',       'first_partial' => 92, 'second_partial' => 90, 'final_exam' => 95, 'final_grade' => 92, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['ana@uncpggl.edu.ni'],      'career_id' => $careerIds['ENF-001'], 'subject_name' => 'Fundamentos Enf.',      'first_partial' => 88, 'second_partial' => 85, 'final_exam' => 90, 'final_grade' => 88, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['pedro@uncpggl.edu.ni'],    'career_id' => $careerIds['MED-001'], 'subject_name' => 'Farmacología I',        'first_partial' => 65, 'second_partial' => 70, 'final_exam' => 68, 'final_grade' => 68, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['pedro@uncpggl.edu.ni'],    'career_id' => $careerIds['MED-001'], 'subject_name' => 'Patología General',     'first_partial' => 50, 'second_partial' => 48, 'final_exam' => 45, 'final_grade' => 48, 'status' => 'failed',   'period' => 'I-2026'],
    ['user_id' => $userIds['pedro@uncpggl.edu.ni'],    'career_id' => $careerIds['MED-001'], 'subject_name' => 'Microbiología II',      'first_partial' => 75, 'second_partial' => 72, 'final_exam' => 78, 'final_grade' => 75, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['gabriela@uncpggl.edu.ni'], 'career_id' => $careerIds['CON-001'], 'subject_name' => 'Contabilidad II',       'first_partial' => 88, 'second_partial' => 90, 'final_exam' => 85, 'final_grade' => 88, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['gabriela@uncpggl.edu.ni'], 'career_id' => $careerIds['CON-001'], 'subject_name' => 'Finanzas I',            'first_partial' => 92, 'second_partial' => 88, 'final_exam' => 94, 'final_grade' => 91, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['gabriela@uncpggl.edu.ni'], 'career_id' => $careerIds['CON-001'], 'subject_name' => 'Derecho Mercantil',     'first_partial' => 55, 'second_partial' => 58, 'final_exam' => 52, 'final_grade' => 55, 'status' => 'failed',   'period' => 'I-2026'],
    ['user_id' => $userIds['diego@uncpggl.edu.ni'],    'career_id' => $careerIds['ISI-001'], 'subject_name' => 'Programación I',        'first_partial' => 95, 'second_partial' => 92, 'final_exam' => 98, 'final_grade' => 95, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['diego@uncpggl.edu.ni'],    'career_id' => $careerIds['ISI-001'], 'subject_name' => 'Matemática Discreta',   'first_partial' => 88, 'second_partial' => 85, 'final_exam' => 90, 'final_grade' => 88, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['valeria@uncpggl.edu.ni'],  'career_id' => $careerIds['ARQ-001'], 'subject_name' => 'Diseño Arquitectónico IV','first_partial'=> 82, 'second_partial' => 85, 'final_exam' => 80, 'final_grade' => 82, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['valeria@uncpggl.edu.ni'],  'career_id' => $careerIds['ARQ-001'], 'subject_name' => 'Urbanismo II',           'first_partial' => 45, 'second_partial' => 50, 'final_exam' => 48, 'final_grade' => 48, 'status' => 'failed',   'period' => 'I-2026'],
    ['user_id' => $userIds['marcos@uncpggl.edu.ni'],   'career_id' => $careerIds['AGR-001'], 'subject_name' => 'Fitotecnia II',          'first_partial' => 78, 'second_partial' => 80, 'final_exam' => 75, 'final_grade' => 78, 'status' => 'approved', 'period' => 'I-2026'],
    ['user_id' => $userIds['marcos@uncpggl.edu.ni'],   'career_id' => $careerIds['AGR-001'], 'subject_name' => 'Suelos y Fertilización', 'first_partial' => 70, 'second_partial' => 68, 'final_exam' => 72, 'final_grade' => 70, 'status' => 'approved', 'period' => 'I-2026'],
];
        foreach ($grades as &$g) { $g['created_at'] = now(); $g['updated_at'] = now(); }
        DB::table('grades')->insert($grades);

        $scholarships = [
            ['user_id' => $userIds['maria@uncpggl.edu.ni'],  'type' => 'academica',       'coverage_percentage' => 50.00, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'description' => 'Beca por excelencia académica.'],
            ['user_id' => $userIds['carlos@uncpggl.edu.ni'], 'type' => 'socioeconómica',  'coverage_percentage' => 75.00, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'description' => 'Beca socioeconómica.'],
            ['user_id' => $userIds['jose@uncpggl.edu.ni'],   'type' => 'deportiva',       'coverage_percentage' => 25.00, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'description' => 'Beca por representación en atletismo.'],
        ];
        foreach ($scholarships as &$s) { $s['created_at'] = now(); $s['updated_at'] = now(); }
        DB::table('scholarships')->insert($scholarships);

        $tutorings = [
    ['user_id' => $userIds['maria@uncpggl.edu.ni'],  'subject_name' => 'Histología',       'tutor_name' => 'Dr. Roberto Chavarría', 'scheduled_at' => '2026-06-12 14:00:00', 'status' => 'scheduled', 'meeting_link_or_place' => 'Aula 201',        'cost' => 10.00, 'exam_cost' => 10.00, 'tutoring_paid' => false, 'exam_paid' => false],
    ['user_id' => $userIds['carlos@uncpggl.edu.ni'], 'subject_name' => 'Base de Datos II', 'tutor_name' => 'Ing. Luis Centeno',     'scheduled_at' => '2026-06-14 10:00:00', 'status' => 'scheduled', 'meeting_link_or_place' => 'Lab. Informática', 'cost' => 10.00, 'exam_cost' => 10.00, 'tutoring_paid' => false, 'exam_paid' => false],
    ['user_id' => $userIds['jose@uncpggl.edu.ni'],   'subject_name' => 'Estructuras I',    'tutor_name' => 'Ing. Pedro Gutiérrez',  'scheduled_at' => '2026-06-13 15:00:00', 'status' => 'scheduled', 'meeting_link_or_place' => 'Aula 401',        'cost' => 10.00, 'exam_cost' => 10.00, 'tutoring_paid' => false, 'exam_paid' => false],
    ['user_id' => $userIds['pedro@uncpggl.edu.ni'],  'subject_name' => 'Patología General','tutor_name' => 'Dra. Carmen López',     'scheduled_at' => '2026-06-15 09:00:00', 'status' => 'scheduled', 'meeting_link_or_place' => 'Aula 202',        'cost' => 10.00, 'exam_cost' => 10.00, 'tutoring_paid' => false, 'exam_paid' => false],
    ['user_id' => $userIds['gabriela@uncpggl.edu.ni'],'subject_name'=> 'Derecho Mercantil','tutor_name' => 'Lic. Rosa Espinoza',    'scheduled_at' => '2026-06-16 14:00:00', 'status' => 'scheduled', 'meeting_link_or_place' => 'Aula 103',        'cost' => 10.00, 'exam_cost' => 10.00, 'tutoring_paid' => false, 'exam_paid' => false],
    ['user_id' => $userIds['valeria@uncpggl.edu.ni'], 'subject_name'=> 'Urbanismo II',     'tutor_name' => 'Arq. Sandra Potosme',   'scheduled_at' => '2026-06-17 10:00:00', 'status' => 'scheduled', 'meeting_link_or_place' => 'Aula 301',        'cost' => 10.00, 'exam_cost' => 10.00, 'tutoring_paid' => false, 'exam_paid' => false],
];
foreach ($tutorings as &$t) { $t['created_at'] = now(); $t['updated_at'] = now(); }
DB::table('tutorings')->insert($tutorings);

        $calendars = [
            ['title' => 'Inscripción Semestre I-2026', 'description' => 'Periodo de inscripción para el primer semestre del año 2026.', 'type' => 'inscription', 'start_date' => '2026-01-15', 'end_date' => '2026-02-05', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Inscripción Semestre II-2026', 'description' => 'Periodo de inscripción para el segundo semestre del año 2026.', 'type' => 'inscription', 'start_date' => '2026-07-20', 'end_date' => '2026-08-10', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Exámenes Finales Semestre I', 'description' => 'Periodo de exámenes finales del primer semestre.', 'type' => 'exam', 'start_date' => '2026-06-15', 'end_date' => '2026-06-30', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Vacaciones de Semestre', 'description' => 'Vacaciones entre semestres.', 'type' => 'vacation', 'start_date' => '2026-07-01', 'end_date' => '2026-07-19', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Vacaciones de Verano', 'description' => 'Vacaciones de fin de año.', 'type' => 'vacation', 'start_date' => '2026-12-18', 'end_date' => '2027-01-10', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Fecha límite de pago de mensualidad Junio', 'description' => 'Último día para pagar la mensualidad de junio sin recargo.', 'type' => 'deadline', 'start_date' => '2026-06-05', 'end_date' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Entrega de Notas Semestre I', 'description' => 'Fecha límite para que los docentes entreguen las notas del primer semestre.', 'type' => 'deadline', 'start_date' => '2026-07-05', 'end_date' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('academic_calendars')->insert($calendars);

        $events = [
            ['title' => 'Feria del Conocimiento 2026', 'description' => 'Exposición de proyectos estudiantiles y charlas académicas de todas las carreras.', 'location' => 'Auditorio Principal', 'event_date' => '2026-06-20 09:00:00', 'organizer' => 'Vicerrectorado Académico', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Torneo Interfacultades de Fútbol', 'description' => 'Competencia deportiva entre las facultades de la universidad.', 'location' => 'Cancha Universitaria', 'event_date' => '2026-06-25 14:00:00', 'organizer' => 'Dirección de Bienestar Estudiantil', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Conferencia: Inteligencia Artificial en la Educación', 'description' => 'Charla sobre el uso de IA en el ámbito educativo y profesional.', 'location' => 'Aula Magna', 'event_date' => '2026-06-18 10:00:00', 'organizer' => 'Facultad de Ciencias y Tecnología', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Jornada de Bienestar Estudiantil', 'description' => 'Actividades de salud, recreación y convivencia para estudiantes.', 'location' => 'Campus Universitario', 'event_date' => '2026-06-22 08:00:00', 'organizer' => 'Dirección de Bienestar Estudiantil', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Seminario de Investigación', 'description' => 'Presentación de investigaciones realizadas por docentes y estudiantes.', 'location' => 'Auditorio Principal', 'event_date' => '2026-07-10 09:00:00', 'organizer' => 'Vicerrectorado de Investigación', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('university_events')->insert($events);
    }
}
