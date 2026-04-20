<script setup>
import { computed, onMounted, reactive, ref } from 'vue';

const accountTypes = ['asset', 'liability', 'equity', 'income', 'expense'];

const today = new Date().toISOString().slice(0, 10);
const monthStart = `${today.slice(0, 8)}01`;

const state = reactive({
    loading: false,
    saving: false,
    deletingEntryId: null,
    error: '',
    success: '',
    accounts: [],
    entries: [],
    trialBalance: [],
    summary: {
        total_debit: 0,
        total_credit: 0,
    },
});

const reportFilters = reactive({
    from: monthStart,
    to: today,
});

const entryForm = reactive({
    entry_date: today,
    description: '',
    lines: [
        { account_id: null, type: 'debit', amount: null, line_description: '' },
        { account_id: null, type: 'credit', amount: null, line_description: '' },
    ],
});

const fieldErrors = ref({});

const debitTotal = computed(() =>
    entryForm.lines
        .filter((line) => line.type === 'debit')
        .reduce((sum, line) => sum + (Number(line.amount) || 0), 0)
);

const creditTotal = computed(() =>
    entryForm.lines
        .filter((line) => line.type === 'credit')
        .reduce((sum, line) => sum + (Number(line.amount) || 0), 0)
);

const isBalanced = computed(() => Math.abs(debitTotal.value - creditTotal.value) < 0.00001);

const canSubmit = computed(() => {
    const validLines = entryForm.lines.every(
        (line) =>
            line.account_id &&
            line.type &&
            Number(line.amount) > 0
    );

    return validLines && entryForm.lines.length >= 2 && isBalanced.value && !state.saving;
});

function resetMessages() {
    state.error = '';
    state.success = '';
}

function money(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function addLine() {
    entryForm.lines.push({
        account_id: null,
        type: 'debit',
        amount: null,
        line_description: '',
    });
}

function removeLine(index) {
    if (entryForm.lines.length <= 2) {
        return;
    }

    entryForm.lines.splice(index, 1);
}

function formatDateOnly(value) {
    if (!value) {
        return '';
    }

    const valueAsString = String(value);
    const yyyyMmDdPrefix = valueAsString.match(/^(\d{4}-\d{2}-\d{2})/);

    if (yyyyMmDdPrefix) {
        return yyyyMmDdPrefix[1];
    }

    const parsedDate = new Date(valueAsString);

    if (Number.isNaN(parsedDate.getTime())) {
        return valueAsString;
    }

    return parsedDate.toISOString().slice(0, 10);
}

async function fetchAccounts() {
    const response = await window.axios.get('/api/accounts');
    state.accounts = response.data.data;
}

async function fetchEntries() {
    const response = await window.axios.get('/api/journal-entries');
    state.entries = response.data.data;
}

async function fetchTrialBalance() {
    const response = await window.axios.get('/api/reports/trial-balance', {
        params: reportFilters,
    });
    state.trialBalance = response.data.data;
    state.summary = response.data.summary;
}

function clearEntryForm() {
    entryForm.entry_date = today;
    entryForm.description = '';
    entryForm.lines = [
        { account_id: null, type: 'debit', amount: null, line_description: '' },
        { account_id: null, type: 'credit', amount: null, line_description: '' },
    ];
}

async function submitEntry() {
    resetMessages();
    fieldErrors.value = {};
    state.saving = true;

    try {
        const payload = {
            entry_date: entryForm.entry_date,
            description: entryForm.description || null,
            lines: entryForm.lines.map((line) => ({
                account_id: line.account_id,
                type: line.type,
                amount: Number(line.amount || 0),
                line_description: line.line_description || null,
            })),
        };

        await window.axios.post('/api/journal-entries', payload);

        state.success = 'Journal entry saved.';
        clearEntryForm();
        await Promise.all([fetchEntries(), fetchTrialBalance()]);
    } catch (error) {
        if (error.response?.status === 422) {
            fieldErrors.value = error.response.data.errors || {};
            state.error = 'Please fix validation errors.';
        } else {
            state.error = 'Unable to save journal entry.';
        }
    } finally {
        state.saving = false;
    }
}

async function deleteEntry(entryId) {
    resetMessages();
    state.deletingEntryId = entryId;

    try {
        await window.axios.delete(`/api/journal-entries/${entryId}`);
        state.success = 'Journal entry deleted.';
        await Promise.all([fetchEntries(), fetchTrialBalance()]);
    } catch (error) {
        state.error = 'Unable to delete journal entry.';
    } finally {
        state.deletingEntryId = null;
    }
}

async function bootstrap() {
    state.loading = true;
    resetMessages();

    try {
        await Promise.all([fetchAccounts(), fetchEntries(), fetchTrialBalance()]);
    } catch (error) {
        state.error = 'Failed to load accounting data.';
    } finally {
        state.loading = false;
    }
}

onMounted(() => {
    bootstrap();
});
</script>

<template>
    <main class="app-shell">
        <div class="hero">
            <p></p>
            <h1>Mini Accounting Module</h1>
            <p>Double-entry journals with live balancing and trial balance reporting.</p>
        </div>

        <p v-if="state.error" class="status status-error">{{ state.error }}</p>
        <p v-if="state.success" class="status status-success">{{ state.success }}</p>

        <section class="grid">
            <article class="card card-wide">
                <div class="card-title">
                    <h2>Journal Entry</h2>
                    <button type="button" class="ghost" @click="addLine">+ Add Line</button>
                </div>

                <div class="form-grid">
                    <label>
                        Date
                        <input v-model="entryForm.entry_date" type="date" />
                    </label>
                    <label>
                        Description
                        <input v-model="entryForm.description" type="text" placeholder="Entry description" />
                    </label>
                </div>

                <p v-if="fieldErrors.entry_date" class="field-error">{{ fieldErrors.entry_date[0] }}</p>
                <p v-if="fieldErrors.lines" class="field-error">{{ fieldErrors.lines[0] }}</p>

                <div class="line-header">
                    <span>Account</span>
                    <span>Type</span>
                    <span>Amount</span>
                    <span>Line Description</span>
                    <span></span>
                </div>

                <div v-for="(line, index) in entryForm.lines" :key="index" class="line-row">
                    <select v-model.number="line.account_id">
                        <option :value="null">Select account</option>
                        <option v-for="account in state.accounts" :key="account.id" :value="account.id">
                            {{ account.code }} - {{ account.name }}
                        </option>
                    </select>

                    <select v-model="line.type">
                        <option value="debit">Debit</option>
                        <option value="credit">Credit</option>
                    </select>

                    <input v-model.number="line.amount" type="number" min="0.01" step="0.01" placeholder="0.00" />
                    <input v-model="line.line_description" type="text" placeholder="Optional note" />
                    <button type="button" class="icon" @click="removeLine(index)">Remove</button>
                </div>

                <div class="totals">
                    <p>Debit: <strong>{{ money(debitTotal) }}</strong></p>
                    <p>Credit: <strong>{{ money(creditTotal) }}</strong></p>
                    <p :class="isBalanced ? 'balanced' : 'unbalanced'">
                        {{ isBalanced ? 'Balanced' : 'Not Balanced' }}
                    </p>
                </div>

                <button type="button" class="primary" :disabled="!canSubmit || state.loading" @click="submitEntry">
                    {{ state.saving ? 'Saving...' : 'Save Journal Entry' }}
                </button>
            </article>

            <article class="card">
                <h2>Accounts</h2>
                <ul class="accounts">
                    <li v-for="type in accountTypes" :key="type">
                        <h3>{{ type }}</h3>
                        <p
                            v-for="account in state.accounts.filter((item) => item.type === type)"
                            :key="account.id"
                        >
                            {{ account.code }} - {{ account.name }}
                        </p>
                    </li>
                </ul>
            </article>
        </section>

        <section class="grid">
            <article class="card">
                <div class="card-title">
                    <h2>Trial Balance</h2>
                    <button type="button" class="ghost" @click="fetchTrialBalance">Refresh</button>
                </div>

                <div class="form-grid">
                    <label>
                        From
                        <input v-model="reportFilters.from" type="date" />
                    </label>
                    <label>
                        To
                        <input v-model="reportFilters.to" type="date" />
                    </label>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Account</th>
                                <th>Type</th>
                                <th class="right">Debit</th>
                                <th class="right">Credit</th>
                                <th class="right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in state.trialBalance" :key="row.id">
                                <td>{{ row.code }}</td>
                                <td>{{ row.name }}</td>
                                <td class="capitalize">{{ row.type }}</td>
                                <td class="right">{{ money(row.total_debit) }}</td>
                                <td class="right">{{ money(row.total_credit) }}</td>
                                <td class="right">{{ money(row.balance) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">Totals</th>
                                <th class="right">{{ money(state.summary.total_debit) }}</th>
                                <th class="right">{{ money(state.summary.total_credit) }}</th>
                                <th class="right">{{ money(state.summary.total_debit - state.summary.total_credit) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </article>

            <article class="card">
                <div class="card-title">
                    <h2>Recent Journal Entries</h2>
                    <button type="button" class="ghost" @click="fetchEntries">Refresh</button>
                </div>

                <div v-if="state.entries.length === 0" class="empty">No journal entries yet.</div>

                <div v-for="entry in state.entries" :key="entry.id" class="entry-item">
                    <header>
                        <div class="entry-meta">
                            <p>
                                <strong>{{ formatDateOnly(entry.entry_date) }}</strong>
                            </p>
                            <button
                                type="button"
                                class="icon danger"
                                :disabled="state.deletingEntryId === entry.id"
                                @click="deleteEntry(entry.id)"
                            >
                                {{ state.deletingEntryId === entry.id ? 'Deleting...' : 'Delete' }}
                            </button>
                        </div>
                        <p>{{ entry.description || 'No description' }}</p>
                    </header>
                    <table>
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Type</th>
                                <th>Line Description</th>
                                <th class="right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in entry.lines" :key="line.id">
                                <td>{{ line.account?.code }} - {{ line.account?.name }}</td>
                                <td class="capitalize">{{ line.type }}</td>
                                <td>{{ line.line_description || '-' }}</td>
                                <td class="right">{{ money(line.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </main>
</template>
