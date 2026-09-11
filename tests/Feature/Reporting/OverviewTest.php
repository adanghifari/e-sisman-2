<?php

namespace Tests\Feature\Reporting;

use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentRelation;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_returns_published_procedures_with_instruction_children(): void
    {
        $data = $this->createOverviewDocuments();
        $user = $data['user'];
        $department = $data['department'];
        $businessFunction = $data['businessFunction'];

        $response = $this->actingAs($user)
            ->get(route('reports.index', [
                'procedure' => 'PS-OVR',
                'department_id' => $department->id,
                'business_function_id' => $businessFunction->id,
            ]));

        $rows = $response->viewData('overviewRows');
        $firstRow = $rows->getCollection()->first();
        $secondRow = $rows->getCollection()->skip(1)->first();

        $response->assertOk();
        $this->assertSame(10, $rows->perPage());
        $this->assertSame('PS-OVR-01', $firstRow['number']);
        $this->assertSame('00.01', $firstRow['revision']);
        $this->assertSame('PS-OVR-01', $secondRow['number']);
        $this->assertSame('00.00', $secondRow['revision']);
        $this->assertSame('IK-OVR-01', $firstRow['instructions']->first()['number']);
        $this->assertSame('Department Overview', $firstRow['departments']->first());
        $this->assertSame('Fungsi Overview', $firstRow['business_function']);
        $this->assertSame(0, collect($response->viewData('overviewSummary'))->firstWhere('label', 'Total Manual')['value']);
        $this->assertSame(2, collect($response->viewData('overviewSummary'))->firstWhere('label', 'Total Prosedur')['value']);
        $this->assertSame(1, collect($response->viewData('overviewSummary'))->firstWhere('label', 'Total Instruksi Kerja')['value']);
        $this->assertSame(3, $response->viewData('trendStatistics')['total']);
        $this->assertArrayHasKey(now()->year, $response->viewData('yearOptions'));
        $this->assertSame(3, collect($response->viewData('businessFunctionStatistics')['items'])->firstWhere('label', 'Fungsi Overview')['value']);
        $this->assertEquals([
            'procedure' => 'PS-OVR',
            'instruction' => '',
            'department_id' => (string) $department->id,
            'business_function_id' => (string) $businessFunction->id,
            'year' => now()->year,
        ], $response->viewData('overviewFilters'));
    }

    public function test_overview_export_downloads_flat_procedure_and_instruction_rows(): void
    {
        $data = $this->createOverviewDocuments();

        $response = $this->actingAs($data['user'])
            ->get(route('reports.export', [
                'procedure' => 'PS-OVR',
                'department_id' => $data['department']->id,
                'business_function_id' => $data['businessFunction']->id,
            ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('No,Kategori,"Nama Dokumen","Nomor Dokumen",Revisi,"Induk Prosedur",Department,Proses/Fungsi,"Tanggal Terbit",Status', $content);
        $this->assertStringContainsString('1,Prosedur,"Prosedur Bongkar Muat",PS-OVR-01,00.01,-,"Department Overview","Fungsi Overview"', $content);
        $this->assertStringContainsString('2,"Instruksi Kerja","Instruksi Kerja Bongkar Curah",IK-OVR-01,00.00,"PS-OVR-01 - Prosedur Bongkar Muat","Department Overview","Fungsi Overview"', $content);
        $this->assertStringContainsString('3,Prosedur,"Prosedur Bongkar Muat Lama",PS-OVR-01,00.00,-,"Department Overview","Fungsi Overview"', $content);
        $this->assertStringNotContainsString('Request Obsolete Tidak Ditampilkan', $content);
    }

    private function createOverviewDocuments(): array
    {
        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $approved = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $obsolete = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::OBSOLETE]);
        $procedureLevel = DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-2'],
            ['nama_level' => 'Level II', 'nama_dokumen' => 'Dokumen Level II : Prosedur SKMBS', 'sort_order' => 2],
        );
        $instructionLevel = DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-3'],
            ['nama_level' => 'Level III', 'nama_dokumen' => 'Dokumen Level III : Instruksi Kerja', 'sort_order' => 3],
        );
        $type = DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        $department = Department::query()->create([
            'kode_department' => 'OVR',
            'nama_department' => 'Department Overview',
            'is_active' => true,
        ]);
        $businessProcess = BusinessProcess::query()->create([
            'kode' => 'OVR',
            'nama_proses_bisnis' => 'Proses Overview',
            'is_active' => true,
        ]);
        $businessFunction = BusinessFunction::query()->create([
            'kode' => 'OVR',
            'nama_proses_fungsi' => 'Fungsi Overview',
            'is_active' => true,
        ]);

        $procedure = Document::query()->create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $approved->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Bongkar Muat',
            'nomor_dokumen' => 'PS-OVR-01',
            'nomor_revisi' => 1,
            'tanggal_terbit' => now()->toDateString(),
        ]);
        $procedure->departments()->attach($department);

        Document::query()->create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $obsolete->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Prosedur Bongkar Muat Lama',
            'nomor_dokumen' => 'PS-OVR-01',
            'nomor_revisi' => 0,
            'tanggal_terbit' => now()->subDay()->toDateString(),
        ])->departments()->attach($department);

        $instruction = Document::query()->create([
            'm_document_level_id' => $instructionLevel->id,
            'm_status_document_id' => $approved->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Instruksi Kerja Bongkar Curah',
            'nomor_dokumen' => 'IK-OVR-01',
            'nomor_revisi' => 0,
            'tanggal_terbit' => now()->toDateString(),
        ]);
        $instruction->departments()->attach($department);

        $instruction->outgoingRelations()->create([
            'target_document_id' => $procedure->id,
            'target_imported_existing_document_id' => null,
            'relation_type' => DocumentRelation::REFERENCES,
            'created_by' => $user->id,
        ]);

        Document::query()->create([
            'm_document_level_id' => $procedureLevel->id,
            'm_status_document_id' => $obsolete->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Request Obsolete Tidak Ditampilkan',
            'nomor_dokumen' => 'PS-OVR-02',
            'nomor_revisi' => 0,
            'request_type' => 'obsolete',
        ]);

        return [
            'user' => $user,
            'department' => $department,
            'businessFunction' => $businessFunction,
        ];
    }
}
