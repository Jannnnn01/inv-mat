<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

final class InitialCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = Time::now()->toDateTimeString();

        if ($this->db->table('warehouses')->countAllResults() === 0) {
            $this->db->table('warehouses')->insert([
                'code'       => 'PRINCIPAL',
                'name'       => 'Bodega principal',
                'is_main'    => true,
                'active'     => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $units = [
            ['code' => 'UNIDAD', 'name' => 'Unidad', 'symbol' => 'und'],
            ['code' => 'PAQUETE', 'name' => 'Paquete', 'symbol' => 'paq'],
            ['code' => 'CAJA', 'name' => 'Caja', 'symbol' => 'caja'],
            ['code' => 'RESMA', 'name' => 'Resma', 'symbol' => 'resma'],
            ['code' => 'LITRO', 'name' => 'Litro', 'symbol' => 'L'],
            ['code' => 'KILOGRAMO', 'name' => 'Kilogramo', 'symbol' => 'kg'],
            ['code' => 'METRO', 'name' => 'Metro', 'symbol' => 'm'],
            ['code' => 'GALON', 'name' => 'Galón', 'symbol' => 'gal'],
        ];

        foreach ($units as $unit) {
            $exists = $this->db->table('measurement_units')->where('code', $unit['code'])->countAllResults() > 0;
            if (! $exists) {
                $this->db->table('measurement_units')->insert($unit + [
                    'active'     => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
