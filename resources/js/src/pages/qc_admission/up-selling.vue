<script setup>
import { useUpSellingStore } from "@/stores/useUpSellingStore";
import UpSellingFormDialog from "@/views/qc-admission/up-selling/UpSellingFormDialog.vue";

const store = useUpSellingStore();

const showDialog = ref(false);
const editItem = ref(null);
const search = ref("");

const mockRecords = ref([
    {
        id: 1,
        tanggal: "29/06/2026, 08.00.00",
        no_reg: "813500",
        nama_pasien: "ELLY MAYA, NY",
        jaminan: "BPJS",
        rekomendasi_kelas: "Kelas 1",
        kelas_diambil: "Kelas 2",
        alasan: "Budget terbatas",
        petugas: "Nurul",
        status: "Tidak Berhasil",
        note: "",
    },
]);

const headers = [
    { title: "Tanggal", key: "tanggal", sortable: true },
    { title: "NoReg", key: "no_reg", sortable: true },
    { title: "Nama Pasien", key: "nama_pasien", sortable: true },
    { title: "Jaminan", key: "jaminan", sortable: true },
    {
        title: "Rek. Kelas",
        key: "rekomendasi_kelas",
        sortable: true,
        align: "center",
    },
    {
        title: "Kelas Diambil",
        key: "kelas_diambil",
        sortable: true,
        align: "center",
    },
    { title: "Status", key: "status", sortable: true, align: "center" },
    { title: "Petugas", key: "petugas", sortable: true },
    {
        title: "Aksi",
        key: "actions",
        sortable: false,
        align: "center",
        width: "90px",
    },
];

function statusColor(s) {
    return (
        { Berhasil: "success", "Tidak Berhasil": "error", Pending: "warning" }[
            s
        ] ?? "secondary"
    );
}

function openAdd() {
    editItem.value = null;
    showDialog.value = true;
}
function openEdit(item) {
    editItem.value = { ...item };
    showDialog.value = true;
}
function onSaved() {
    showDialog.value = false;
}

onMounted(() => {
    /* store.fetchRecords() */
});
</script>

<template>
    <div>
        <div class="d-flex align-center justify-space-between mb-6">
            <div>
                <h4 class="text-h5 font-weight-bold">Up Selling</h4>
                <p class="text-body-2 text-medium-emphasis mb-0">
                    Kelola data up selling layanan kamar pasien
                </p>
            </div>
            <VBtn color="primary" prepend-icon="ri-add-line" @click="openAdd"
                >Input Up Selling</VBtn
            >
        </div>

        <VCard>
            <VCardItem>
                <template #append>
                    <VTextField
                        v-model="search"
                        placeholder="Cari..."
                        prepend-inner-icon="ri-search-line"
                        variant="outlined"
                        density="compact"
                        hide-details
                        style="min-width: 220px"
                    />
                </template>
            </VCardItem>
            <VDivider />
            <VDataTable
                :headers="headers"
                :items="mockRecords"
                :loading="store.loading"
                :search="search"
                density="compact"
                hover
                :items-per-page="10"
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
                <template #item.actions="{ item }">
                    <div class="d-flex gap-1 justify-center">
                        <VBtn
                            icon
                            size="x-small"
                            variant="text"
                            color="info"
                            @click="openEdit(item)"
                        >
                            <VIcon icon="ri-pencil-line" />
                        </VBtn>
                        <VBtn
                            icon
                            size="x-small"
                            variant="text"
                            color="error"
                            @click="
                                mockRecords = mockRecords.filter(
                                    (r) => r.id !== item.id,
                                )
                            "
                        >
                            <VIcon icon="ri-delete-bin-line" />
                        </VBtn>
                    </div>
                </template>
                <template #no-data>
                    <div class="text-center py-8 text-medium-emphasis">
                        <VIcon
                            icon="ri-arrow-up-circle-line"
                            size="36"
                            class="mb-2"
                        />
                        <p class="mb-0">Belum ada data up selling</p>
                    </div>
                </template>
            </VDataTable>
        </VCard>

        <UpSellingFormDialog
            v-model="showDialog"
            :edit-item="editItem"
            @saved="onSaved"
        />
    </div>
</template>
