<?php

namespace Database\Seeders;

use App\Models\ActionReason;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\StatusAction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class DrivingForceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $driving_forces;
    protected $driving_force_ratings;

    /**
     * Domain-specific synthetic foresight rationales for automotive and energy transition.
     * Structured by driving force keyword and action status (Monitoring vs Decided Plan).
     *
     * @var array<string, array<string, array<int, string>>>
     */
    protected const SYNTHETIC_REASONS = [
        'Euro-5 Plan' => [
            'Monitoring' => [
                'Pemantauan berkala terhadap rancangan permen LHK mengenai ambang batas emisi Euro-5 serta ketersediaan pasokan BBM ultra-rendah sulfur di pasar domestik.',
                'Kajian teknis bersama asosiasi industri Gaikindo mengenai kesiapan fasilitas uji tipe emisi dan timeline penyesuaian sertifikasi kendaraan komersial.',
            ],
            'Decided Plan' => [
                'Komite manajemen menyetujui program adaptasi mesin Euro-5 untuk model tahun 2026 dan mengamankan kontrak pasokan catalytic converter generasi terbaru.',
                'Pengesahan alokasi anggaran investasi fasilitas uji emisi internal pabrik serta finalisasi kalibrasi powertrain bersama prinsipal.',
            ],
        ],
        'Import Regulation' => [
            'Monitoring' => [
                'Monitoring perumusan Permendag terkait pengetatan tata niaga impor dan verifikasi teknis untuk suku cadang serta komponen sub-assembly.',
                'Simulasi dampak lead-time pengurusan Persetujuan Impor (PI) terhadap buffer stock dan kontinuitas lini produksi semester depan.',
            ],
            'Decided Plan' => [
                'Penetapan rencana kontingensi pengadaan lokal untuk komponen non-kritis guna meminimalkan risiko hambatan perizinan impor.',
                'Pembentukan gugus tugas percepatan kepatuhan perizinan impor dan integrasi sistem manifest pengiriman dengan INSW.',
            ],
        ],
        'TKDN Regulation' => [
            'Monitoring' => [
                'Monitoring regulasi perhitungan nilai Tingkat Komponen Dalam Negeri (TKDN) xEV menuju target ambang batas minimum 60% pada 2030.',
                'Pemetaan kesiapan rantai pasok lokal untuk perakitan modul baterai, sistem inverter, dan komponen struktural utama.',
            ],
            'Decided Plan' => [
                'Direksi menyetujui roadmap lokalisasi baterai pack dan kemitraan strategis dengan pemasok tier-1 lokal untuk mempertahankan insentif PPN DTP.',
                'Eksekusi perjanjian alih teknologi manufaktur modul elektronik daya guna memenuhi target audit kepatuhan TKDN kuartal IV.',
            ],
        ],
        'Interest Rate' => [
            'Monitoring' => [
                'Pemantauan transmisi kenaikan BI-Rate terhadap suku bunga dasar kredit pembiayaan kendaraan bermotor dan likuiditas perbankan.',
                'Analisis elastisitas permintaan kendaraan segmen mobil keluarga menengah akibat pengetatan credit scoring lembaga multifinance.',
            ],
            'Decided Plan' => [
                'Pengesahan program subsidi bunga cicilan bersama perusahaan pembiayaan captive untuk menjaga keterjangkauan angsuran konsumen.',
                'Peluncuran skema promosi bunga rendah tetap (fixed rate) hingga 3 tahun pertama hasil negosiasi dengan konsorsium perbankan rekanan.',
            ],
        ],
        'Loan Tanure' => [
            'Monitoring' => [
                'Pengamatan tren perpanjangan tenor cicilan kredit mobil hingga 5-7 tahun sebagai respons atas penurunan kemampuan cicil bulanan nasabah.',
                'Evaluasi risiko NPF (Non-Performing Financing) jangka panjang dan kecukupan jaminan fidusia pada portofolio kredit tenor panjang.',
            ],
            'Decided Plan' => [
                'Penerapan skema pembiayaan balloon payment terstruktur dengan jaminan buyback guarantee untuk memitigasi risiko kredit jangka panjang.',
                'Kemitraan eksklusif dengan lembaga pembiayaan syariah dan konvensional untuk opsi tenor fleksibel dengan perlindungan asuransi komprehensif.',
            ],
        ],
        'Minimum Wage' => [
            'Monitoring' => [
                'Pemantauan penetapan Upah Minimum Provinsi (UMP) dan dinamika negosiasi upah sektoral di sentra manufaktur otomotif Karawang dan Bekasi.',
                'Kajian sensitivitas kenaikan biaya tenaga kerja langsung terhadap margin kontribusi per unit kendaraan segmen mass-market.',
            ],
            'Decided Plan' => [
                'Pengesahan rencana investasi otomasi lini pengelasan bodi dan efisiensi logistik internal untuk mengimbangi eskalasi biaya tenaga kerja.',
                'Penerapan program lean manufacturing terpadu dan upskilling operator teknis untuk meningkatkan produktivitas per jam kerja.',
            ],
        ],
        'LFP Battery' => [
            'Monitoring' => [
                'Tracking perkembangan teknologi kimia baterai Lithium Iron Phosphate (LFP) generasi ketiga dengan peningkatan kepadatan energi gravimetrik.',
                'Benchmarking keekonomian sel LFP vs NMC dari segi biaya per kWh, kestabilan termal, dan siklus hidup (cycle life) di iklim tropis.',
            ],
            'Decided Plan' => [
                'Penyusunan spesifikasi teknis dan studi kelayakan adopsi platform baterai LFP untuk lini produk xEV perkotaan hemat biaya.',
                'Penandatanganan nota kesepahaman (MoU) pasokan sel LFP jangka panjang dengan konsorsium industri baterai domestik.',
            ],
        ],
        'Solid Battery' => [
            'Monitoring' => [
                'Pemantauan riset global dan pendaftaran paten teknologi solid-state battery untuk kendaraan listrik generasi berikutnya.',
                'Evaluasi kesiapan proses manufaktur skala pilot dan proyeksi penurunan biaya elektrolit padat menuju komersialisasi 2030.',
            ],
            'Decided Plan' => [
                'Pembentukan tim kerja eksplorasi teknologi masa depan untuk menjajaki kolaborasi riset dengan konsorsium baterai nasional.',
                'Alokasi dana riset tahap awal untuk simulasi integrasi termal sel solid-state pada arsitektur sasis kendaraan masa depan.',
            ],
        ],
        'EV Driving Range' => [
            'Monitoring' => [
                'Analisis kepuasan pelanggan terkait jarak tempuh aktual baterai dan persepsi range anxiety di kalangan komuter antarkota.',
                'Uji coba efisiensi konsumsi daya kWh/100km pada kondisi beban pendingin kabin maksimal dan kontur jalan berbukit perkotaan.',
            ],
            'Decided Plan' => [
                'Penyempurnaan aerodinamika bodi dan kalibrasi sistem regenerative braking cerdas untuk memperpanjang daya jangkau operasional 15%.',
                'Pengembangan modul thermal management baterai canggih yang meminimalkan degradasi kapasitas pada temperatur lingkungan tinggi.',
            ],
        ],
        'NDC Target' => [
            'Monitoring' => [
                'Monitoring pembaruan target Enhanced NDC Indonesia dan regulasi batas atas emisi gas rumah kaca pada sektor industri pengolahan.',
                'Inventarisasi emisi GRK Scope 1, 2, dan 3 di seluruh fasilitas perakitan dan gudang logistik nasional.',
            ],
            'Decided Plan' => [
                'Penyusunan target dekarbonisasi operasional pabrik net-zero emission 2035 didukung transisi 100% listrik hijau tersertifikasi REC.',
                'Pemasangan instalasi PLTS atap tambahan sebesar 4,5 MWp pada kompleks pabrik utama untuk mereduksi intensitas emisi produksi.',
            ],
        ],
        'COP 28' => [
            'Monitoring' => [
                'Telaah kesepakatan global COP28 terkait transisi berkeadilan keluar dari bahan bakar fosil dan target efisiensi energi industri.',
                'Penilaian kepatuhan terhadap indikator keberlanjutan global yang diwajibkan oleh institusi pendanaan internasional dan investor ESG.',
            ],
            'Decided Plan' => [
                'Pengesahan Masterplan Keberlanjutan Korporasi 2030 yang mencakup inisiatif zero-waste manufacturing dan sirkularitas material.',
                'Penerapan standar audit pengadaan hijau (green procurement) bagi seluruh pemasok komponen otomotif tier-1.',
            ],
        ],
        'EU CBA' => [
            'Monitoring' => [
                'Pemantauan fase transisi Carbon Border Adjustment Mechanism (CBAM) Uni Eropa terhadap ekspor komoditas baja dan aluminium komponen.',
                'Penghitungan embedded carbon pada komponen ekspor dan koordinasi persiapan verifikasi dengan auditor emisi independen.',
            ],
            'Decided Plan' => [
                'Implementasi sistem pemantauan dan pelaporan jejak karbon berbasis digital yang terakreditasi standar CBAM Uni Eropa.',
                'Substitusi bahan baku aluminium dengan varian recycled low-carbon aluminium untuk seluruh lini komponen berorientasi ekspor.',
            ],
        ],
        'Mobility Sharing' => [
            'Monitoring' => [
                'Analisis pertumbuhan penetrasi layanan ride-hailing dan car-sharing terhadap pola kepemilikan mobil pribadi generasi milenial dan Gen Z.',
                'Evaluasi potensi pergeseran permintaan dari segmen retail individu ke armada fleet komersial dengan intensitas pemakaian tinggi.',
            ],
            'Decided Plan' => [
                'Peluncuran skema kemitraan armada fleet terintegrasi dengan paket perawatan berkala dan sistem telematika manajemen armada real-time.',
                'Penyediaan varian kendaraan khusus armada sharing dengan durabilitas interior tinggi dan efisiensi konsumsi bahan bakar optimal.',
            ],
        ],
        'Purchasing Power' => [
            'Monitoring' => [
                'Pemantauan indeks keyakinan konsumen dan pergeseran alokasi pengeluaran masyarakat untuk barang kebutuhan primer vs sekunder.',
                'Kajian penurunan volume penjualan kendaraan segmen low-MPV akibat tertekannya disposable income segmen menengah ke bawah.',
            ],
            'Decided Plan' => [
                'Pengembangan program promosi penjualan terarah dengan penawaran uang muka terjangkau, gratis biaya servis berkala, dan asuransi all-risk.',
                'Reorientasi paket leasing dengan opsi grace period angsuran untuk menarik kembali minat konsumen segmen pekerja formal.',
            ],
        ],
        'Customer Preference' => [
            'Monitoring' => [
                'Riset tren preferensi konsumen terhadap kendaraan ramah lingkungan, konektivitas digital kabin, dan sistem keselamatan aktif ADAS.',
                'Analisis hasil survey minat pembelian model hybrid (HEV) vs listrik murni (BEV) berdasarkan kemudahan infrastruktur pengisian daya.',
            ],
            'Decided Plan' => [
                'Pengayaan portofolio produk dengan memperbanyak pilihan powertrain hybrid pada model volume-maker terlaris di pasar.',
                'Pembaruan head-unit infotainment berbasis Android Auto & Apple CarPlay nirkabel sebagai standar di seluruh varian unggulan.',
            ],
        ],
        'Introduce xEV' => [
            'Monitoring' => [
                'Pemantauan agresivitas peluncuran model xEV baru oleh kompetitor asal Asia Timur beserta strategi penetapan harga per segmen pasar.',
                'Analisis positioning harga, fitur kelengkapan, dan strategi bundling wall-charger rumahan yang ditawarkan oleh para pesaing.',
            ],
            'Decided Plan' => [
                'Percepatan jadwal pengenalan lini xEV generasi terbaru dengan diferensiasi keandalan purna jual dan jaringan dealer terluas nasional.',
                'Pengesahan paket promo bundling instalasi home-charging gratis dan diskon tarif listrik PLN malam hari untuk pemilik xEV baru.',
            ],
        ],
        'A segment low prices' => [
            'Monitoring' => [
                'Observasi penetrasi model segmen A baru dengan banderol harga sangat terjangkau yang menargetkan pembeli mobil pertama (first-time buyer).',
                'Analisis struktur biaya dan perbandingan value-for-money antara model segmen A baru dengan mobil bekas segmen atas.',
            ],
            'Decided Plan' => [
                'Penyegaran desain eksterior dan peningkatan efisiensi bahan bakar model entry-level untuk mempertahankan loyalitas pasar segmen A.',
                'Penerapan re-engineering komponen non-struktural guna mempertahankan margin profitabilitas tanpa mengurangi standar keselamatan aktif.',
            ],
        ],
        'New comers' => [
            'Monitoring' => [
                'Pemetaan ekspansi produsen otomotif global baru yang masuk ke pasar Indonesia melalui investasi fasilitas manufaktur mandiri.',
                'Kajian strategi penetapan harga agresif (price war) dan garansi baterai seumur hidup yang diterapkan pemain pendatang baru.',
            ],
            'Decided Plan' => [
                'Penguatan value proposition purna jual melalui jaringan servis terpercaya, jaminan ketersediaan suku cadang 24 jam, dan nilai jual kembali yang stabil.',
                'Peningkatan standar garansi baterai traksi menjadi 8 tahun atau 160.000 km guna memberikan ketenangan pikiran maksimal kepada konsumen.',
            ],
        ],
        'Invest xEV Technology' => [
            'Monitoring' => [
                'Pemantauan kesiapan pemasok komponen tier-1 lokal dalam mengalokasikan capex untuk lini produksi komponen powertrain elektrifikasi.',
                'Kajian kesenjangan kapabilitas teknis (skills gap) tenaga kerja pemasok lokal pada perakitan sistem kelistrikan tegangan tinggi.',
            ],
            'Decided Plan' => [
                'Penyelenggaraan program pembinaan teknis dan kerja sama transfer teknologi dengan rantai pasok lokal untuk mendukung industrialisasi xEV.',
                'Pemberian insentif kemitraan investasi bagi pemasok tier-1 yang berhasil mendirikan lini perakitan komponen xEV di dalam negeri.',
            ],
        ],
    ];

    public function __construct()
    {
        $this->driving_forces = json_decode(file_get_contents(__DIR__ . '../../data/driving_force.json'));
        $this->driving_force_ratings = json_decode(file_get_contents(__DIR__ . '../../data/driving_force_rating.json'));
    }

    public function run(): void
    {
        $this->command->info('initial driving force data');
        $this->command->getOutput()->progressStart(count($this->driving_forces));
        foreach ($this->driving_forces as $driving_force) {
            $this->initializeDefaultDrivingForce($driving_force);
            $this->command->getOutput()->progressAdvance();
        }

        $this->command->info('initial driving force rating data');
        $this->command->getOutput()->progressStart(count($this->driving_force_ratings));
        foreach ($this->driving_force_ratings as $rating) {
            $this->initializeDefaultDrivingForceRating($rating);
            $this->command->getOutput()->progressAdvance();
        }
    }

    public function initializeDefaultDrivingForce($driving_force): void
    {
        DrivingForce::firstOrCreate([
            'keyword' => $driving_force->keyword,
        ], [
            'uuid' => Uuid::uuid1(),
            'dimension_id' => $driving_force->dimension_id,
            'description' => $driving_force->description,
            'status' => 'APPROVED',
            'created_by' => User::all()->random()->id,
            'updated_by' => User::all()->random()->id,
            'pic' => User::all()->random()->id,
            'approved_at' => now()->subDays(rand(10, 45)),
        ]);
    }

    public function initializeDefaultDrivingForceRating($rating): void
    {
        $createdRating = DrivingForceRating::firstOrCreate([
            'driving_force_id' => $rating->driving_force_id,
            'status_action_id' => $rating->status_action_id,
        ], [
            'uuid' => Uuid::uuid1(),
            'time_horizon_id' => $rating->time_horizon_id,
            'priority_id' => $rating->priority_id,
            'impact_analysis' => $rating->impact_analysis,
            'uncertainty_analysis' => $rating->uncertainty_analysis,
        ]);

        $df = DrivingForce::find($rating->driving_force_id);
        $statusActions = StatusAction::all()->keyBy('name');
        $monitoringStatus = $statusActions->get('Monitoring') ?? StatusAction::first();
        $decidedStatus = $statusActions->get('Decided Plan') ?? StatusAction::orderBy('id', 'desc')->first();

        $isFinalDecided = ((int) $rating->status_action_id === (int) $decidedStatus->id);

        // Reset previous seeded reasons if re-running seeder so no stale Lorem Ipsum remains
        ActionReason::where('driving_force_rating_id', $createdRating->id)->delete();

        // 1) Historical surveillance milestone (25-45 days ago): initial Monitoring
        $firstDate = now()->subDays(rand(25, 45))->subHours(rand(1, 12))->subMinutes(rand(1, 50));
        ActionReason::create([
            'uuid' => Uuid::uuid1(),
            'driving_force_rating_id' => $createdRating->id,
            'status_action_id' => $monitoringStatus->id,
            'date' => $firstDate,
            'reason' => $this->getSyntheticReason($df?->keyword, 'Monitoring', 1),
        ]);

        // 2) Current assessment milestone (2-8 days ago): Monitoring or Decided Plan based on target rating
        $secondStatus = $isFinalDecided ? $decidedStatus : $monitoringStatus;
        $secondDate = now()->subDays(rand(2, 8))->subHours(rand(1, 12))->subMinutes(rand(1, 50));
        ActionReason::create([
            'uuid' => Uuid::uuid1(),
            'driving_force_rating_id' => $createdRating->id,
            'status_action_id' => $secondStatus->id,
            'date' => $secondDate,
            'reason' => $this->getSyntheticReason($df?->keyword, $secondStatus->name, 2),
        ]);

        $createdRating->status_action_id = $secondStatus->id;
        $createdRating->save();
    }

    /**
     * Resolves an authentic, non-Lorem synthetic rationale based on keyword, status, and chronological step.
     */
    public function getSyntheticReason(?string $keyword, string $statusActionName, int $step = 1): string
    {
        $statusKey = str_contains(strtolower($statusActionName), 'decided') ? 'Decided Plan' : 'Monitoring';

        if ($keyword && isset(self::SYNTHETIC_REASONS[$keyword][$statusKey])) {
            $pool = self::SYNTHETIC_REASONS[$keyword][$statusKey];
            $index = ($step - 1) % count($pool);
            return $pool[$index];
        }

        // Professional synthetic fallback for custom driving forces (strictly no Lorem Ipsum).
        $label = $keyword ?: 'faktor pendorong strategis';
        if ($statusKey === 'Decided Plan') {
            return "Komite manajemen menyetujui rencana aksi mitigasi dan penyesuaian alokasi sumber daya operasional terkait tren terkini pada {$label}.";
        }

        return "Pemantauan berkala dan analisis risiko berkelanjutan terhadap perkembangan indikator {$label} serta implikasinya pada daya saing perusahaan.";
    }
}
