<script setup>
// Aggregated view of all module data — useful for supervisors & admin
const activeModule = ref("quality-control");

const modules = [
    {
        key: "quality-control",
        label: "Quality Control",
        icon: "ri-shield-check-line",
        color: "primary",
    },
    {
        key: "batal-ranap",
        label: "Batal Ranap",
        icon: "ri-close-circle-line",
        color: "error",
    },
    {
        key: "edukasi-lanjutan",
        label: "Edukasi Lanjutan",
        icon: "ri-book-open-line",
        color: "warning",
    },
    {
        key: "up-selling",
        label: "Up Selling",
        icon: "ri-arrow-up-circle-line",
        color: "success",
    },
];

const search = ref("");
const dateFrom = ref(null);
const dateTo = ref(null);

// ── QC Headers ────────────────────────────────────────────────────────────────
const qcHeaders = [
    { title: "Tanggal", key: "tanggal", sortable: true },
    { title: "NoMR", key: "no_mr", sortable: true },
    { title: "Nama Pasien", key: "nama_pasien", sortable: true },
    { title: "Jaminan", key: "jaminan", sortable: true },
    { title: "Status", key: "status", sortable: true, align: "center" },
    { title: "Petugas", key: "petugas", sortable: true },
    {
        title: "Durasi Tunggu",
        key: "durasi_tunggu",
        sortable: true,
        align: "center",
    },
    { title: "Edukasi Kamar", key: "edukasi_kamar", sortable: false },
    { title: "Note", key: "note", sortable: false },
];

// ── Batal Ranap Headers ───────────────────────────────────────────────────────
const batalHeaders = [
    { title: "Tanggal", key: "tanggal", sortable: true },
    { title: "NoReg", key: "no_reg", sortable: true },
    { title: "Keterangan Batal", key: "keterangan_batal", sortable: true },
    { title: "Status OK", key: "status_ok", sortable: true, align: "center" },
    { title: "Diagnosa", key: "diagnosa", sortable: true },
    { title: "Petugas", key: "petugas", sortable: true },
];

// ── Edukasi Headers ───────────────────────────────────────────────────────────
const edukasiHeaders = [
    { title: "Tanggal", key: "tanggal", sortable: true },
    { title: "NoMR", key: "no_mr", sortable: true },
    { title: "Nama Pasien", key: "nama_pasien", sortable: true },
    { title: "Bulan", key: "bulan", sortable: true },
];

// ── UpSelling Headers ─────────────────────────────────────────────────────────
const upSellingHeaders = [
    { title: "Tanggal", key: "tanggal", sortable: true },
    { title: "NoReg", key: "no_reg", sortable: true },
    { title: "Nama Pasien", key: "nama_pasien", sortable: true },
    { title: "Rek. Kelas", key: "rekomendasi_kelas", sortable: true },
    { title: "Kelas Diambil", key: "kelas_diambil", sortable: true },
    { title: "Status", key: "status", sortable: true, align: "center" },
    { title: "Petugas", key: "petugas", sortable: true },
];

// ── Mock data ─────────────────────────────────────────────────────────────────
const qcData = ref([
    {
        id: 1,
        tanggal: "29/06/2026, 19.41.58",
        no_mr: "813500",
        nama_pasien: "ELLY MAYA, NY",
        jaminan: "BPJS",
        status: "Edukasi",
        petugas: "Nurul",
        durasi_tunggu: "10:17:19",
        edukasi_kamar: "",
        note: "",
    },
    {
        id: 2,
        tanggal: "29/06/2026, 18.05.22",
        no_mr: "575360",
        nama_pasien: "IDH SUBINGSEN, NY",
        jaminan: "Umum",
        status: "Edukasi lanjutan",
        petugas: "Reskim",
        durasi_tunggu: "01:32:10",
        edukasi_kamar: "Ruang Mawar",
        note: "Pasien mengerti",
    },
]);
const batalData = ref([
    {
        id: 1,
        tanggal: "29/06/2026, 19.43.45",
        no_reg: "813500",
        keterangan_batal: "Kamar Penuh",
        status_ok: "Pending",
        diagnosa: "Hipertensi",
        petugas: "Nurul",
    },
]);
const edukasiData = ref([
    {
        id: 1,
        tanggal: "29/06/2026",
        no_mr: "813500",
        nama_pasien: "ELLY MAYA, NY",
        bulan: "JUNI",
    },
    {
        id: 2,
        tanggal: "29/06/2026",
        no_mr: "575360",
        nama_pasien: "IDH SUBINGSEN, NY",
        bulan: "JUNI",
    },
]);
const upSellingData = ref([
    {
        id: 1,
        tanggal: "29/06/2026, 08.00.00",
        no_reg: "813500",
        nama_pasien: "ELLY MAYA, NY",
        rekomendasi_kelas: "Kelas 1",
        kelas_diambil: "Kelas 2",
        status: "Tidak Berhasil",
        petugas: "Nurul",
    },
]);

const currentHeaders = computed(() => {
    return (
        {
            "quality-control": qcHeaders,
            "batal-ranap": batalHeaders,
            "edukasi-lanjutan": edukasiHeaders,
            "up-selling": upSellingHeaders,
        }[activeModule.value] ?? []
    );
});

const currentData = computed(() => {
    return (
        {
            "quality-control": qcData.value,
            "batal-ranap": batalData.value,
            "edukasi-lanjutan": edukasiData.value,
            "up-selling": upSellingData.value,
        }[activeModule.value] ?? []
    );
});

function statusColor(s) {
    return (
        {
            Edukasi: "success",
            "Edukasi lanjutan": "warning",
            Masuk: "info",
            OK: "success",
            "Tidak Berhasil": "error",
            Berhasil: "success",
            Pending: "warning",
        }[s] ?? "secondary"
    );
}

function exportCSV() {
    // Placeholder — implement CSV export when API is ready
    alert("Export CSV akan diimplementasikan setelah API tersedia.");
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="d-flex align-center justify-space-between mb-6">
            <div>
                <h4 class="text-h5 font-weight-bold">View Data Input</h4>
                <p class="text-body-2 text-medium-emphasis mb-0">
                    Lihat semua data input per modul
                </p>
            </div>
            <VBtn
                variant="tonal"
                color="success"
                prepend-icon="ri-file-download-line"
                size="small"
                @click="exportCSV"
            >
                Export CSV
            </VBtn>
        </div>

        <!-- Module Tabs -->
        <VCard class="mb-4" variant="outlined">
            <VTabs v-model="activeModule" color="primary" show-arrows>
                <VTab v-for="mod in modules" :key="mod.key" :value="mod.key">
                    <VIcon :icon="mod.icon" size="18" class="me-2" />
                    {{ mod.label }}
                </VTab>
            </VTabs>
        </VCard>

        <!-- Filter Bar -->
        <VCard class="mb-4" variant="outlined">
            <VCardText class="py-3">
                <VRow align="center" dense>
                    <VCol cols="12" md="5">
                        <VTextField
                            v-model="search"
                            placeholder="Cari data..."
                            prepend-inner-icon="ri-search-line"
                            variant="outlined"
                            density="compact"
                            hide-details
                            clearable
                        />
                    </VCol>
                    <VCol cols="12" sm="6" md="3">
                        <VTextField
                            v-model="dateFrom"
                            label="Dari Tanggal"
                            type="date"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </VCol>
                    <VCol cols="12" sm="6" md="3">
                        <VTextField
                            v-model="dateTo"
                            label="Sampai Tanggal"
                            type="date"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </VCol>
                    <VCol cols="12" md="1" class="d-flex justify-end">
                        <VBtn
                            icon
                            variant="text"
                            size="small"
                            title="Reset filter"
                            @click="
                                search = '';
                                dateFrom = null;
                                dateTo = null;
                            "
                        >
                            <VIcon icon="ri-refresh-line" />
                        </VBtn>
                    </VCol>
                </VRow>
            </VCardText>
        </VCard>

        <!-- Data Table -->
        <VCard>
            <VDataTable
                :headers="currentHeaders"
                :items="currentData"
                :search="search"
                density="compact"
                hover
                :items-per-page="15"
            >
                <template #item.status="{ item }">
                    <VChip
                        :color="statusColor(item.status)"
                        size="small"
                        variant="tonal"
                        label
                    >
                        {{ item.status }}
                    </VChip>
                </template>
                <template #item.status_ok="{ item }">
                    <VChip
                        :color="statusColor(item.status_ok)"
                        size="small"
                        variant="tonal"
                        label
                    >
                        {{ item.status_ok ?? "Pending" }}
                    </VChip>
                </template>
                <template #no-data>
                    <div class="text-center py-10 text-medium-emphasis">
                        <VIcon
                            icon="ri-database-2-line"
                            size="40"
                            class="mb-3"
                        />
                        <p class="mb-0">Tidak ada data untuk modul ini</p>
                    </div>
                </template>
            </VDataTable>
        </VCard>
    </div>
</template>
