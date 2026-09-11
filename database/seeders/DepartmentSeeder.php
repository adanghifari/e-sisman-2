<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    private const DEPARTMENTS = [
        'IAU' => 'Internal Audit Unit',
        'PRC' => 'Procurement Department',
        'SABM' => 'SABM Department',
        'MOP' => 'Marine Operation Department',
        'SDV' => 'Strategic Development Department',
        'HSSE' => 'HSSE Department',
        'POP' => 'Port Operation Department',
        'KORKOM' => 'Corporate Communication & Stakeholders Relation Department',
        'PMK' => 'Port Marketing Department',
        'HOP' => 'Handling Operation Department',
        'HCGA' => 'Human Capital & GA Department',
        'ARM' => 'Accounting & RM Department',
        'LCO' => 'Legal & Compliance Department',
        'SOP' => 'Stevedoring Operation Department',
        'BDV' => 'Business Development Department',
        'SFM' => 'Supporting Facility Maintenance Department',
        'OMM' => 'Outreach Marine Marketing Department',
        'OGM' => 'Outreach General Marketing Department',
        'ITSM' => 'IT & Management System Department',
        'CFN' => 'Corporate Finance Department',
        'PFM' => 'Port Facility Maintenance Department',
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $code => $name) {
            DB::table('departments')->updateOrInsert(
                ['kode_department' => $code],
                [
                    'nama_department' => $name,
                    'is_active' => true,
                ],
            );
        }

        DB::table('departments')
            ->whereNotIn('kode_department', array_keys(self::DEPARTMENTS))
            ->delete();
    }
}
