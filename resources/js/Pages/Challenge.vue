<script setup>
import { onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Award, CircleCheck, Flame, Star, Target, Trophy } from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    challenges: { type: Array, default: () => [] },
    streak: { type: Number, default: 0 },
    rewards: { type: Array, default: () => [] },
});

const challengeList = ref(Array.isArray(props.challenges) && props.challenges.length > 0 ? props.challenges : []);
const rewardsList = ref(Array.isArray(props.rewards) ? props.rewards : []);
const claimingId = ref(null);
const claimStatusMessage = ref('');
const claimFailed = ref(false);

function challengePercent(ch) {
    return Math.min(100, Math.round(((ch.progress || 0) / (ch.target || 1)) * 100));
}

onMounted(async () => {
    if (challengeList.value.length === 0) {
        try {
            const res = await fetch('/api/challenges', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                if (Array.isArray(data.challenges)) {
                    challengeList.value = data.challenges;
                }
                if (Array.isArray(data.rewards)) {
                    rewardsList.value = data.rewards;
                }
            }
        } catch {}
    }
});

async function claim(ch) {
    if (claimingId.value || ch.completed) return;
    claimingId.value = ch.id;
    claimFailed.value = false;
    try {
        const res = await fetch('/api/challenges/claim', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken || '',
            },
            body: JSON.stringify({ challenge_id: ch.id }),
        });
        if (res.ok) {
            const data = await res.json();
            ch.completed = true;
            ch.progress = ch.target;
            claimStatusMessage.value = data.message || `Selamat! Bonus +${ch.reward_xp} XP berhasil diklaim.`;
            router.reload({ only: ['auth'] });
        } else {
            claimFailed.value = true;
            claimStatusMessage.value = 'Reward belum bisa diklaim. Coba lagi sebentar.';
        }
    } catch {
        claimFailed.value = true;
        claimStatusMessage.value = 'Gagal mengklaim reward. Periksa koneksi jaringan.';
    } finally {
        claimingId.value = null;
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Challenge Harian" />

        <div class="st-page">
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <span class="st-badge st-badge--primary">Challenge Harian</span>
                    <h1>Tantangan Harian & Bonus XP</h1>
                    <p>Selesaikan target latihan hari ini untuk menjaga streak dan mengumpulkan XP tambahan.</p>
                </div>
                <div class="st-page-header-aside st-summary-box">
                    <span class="st-icon st-icon--gold"><Flame :size="22" /></span>
                    <div>
                        <b>{{ streak }} hari</b>
                        <small>streak saat ini</small>
                    </div>
                </div>
            </header>

            <div v-if="claimStatusMessage" class="st-alert" :class="claimFailed ? 'st-alert--danger' : 'st-alert--success'" role="status">
                <CircleCheck :size="18" />
                <span>{{ claimStatusMessage }}</span>
            </div>

            <div v-if="challengeList.length > 0" class="challenge-stack">
                <section v-for="ch in challengeList" :key="ch.id" class="st-panel">
                    <div class="challenge-head">
                        <span class="st-icon st-icon--lg st-icon--primary"><Target :size="24" /></span>
                        <div class="challenge-copy">
                            <span class="st-eyebrow">Tantangan Harian</span>
                            <h2 class="st-panel-title">{{ ch.title }}</h2>
                            <p class="st-panel-desc">{{ ch.description }}</p>
                        </div>
                        <span class="st-badge st-badge--gold">
                            <Star :size="13" /> +{{ ch.reward_xp }} XP
                        </span>
                    </div>

                    <div class="st-progress-meta">
                        <b>{{ ch.progress }} dari {{ ch.target }} tuntas</b>
                        <span>{{ ch.progress >= ch.target ? 'Target terpenuhi!' : `Tersisa ${Math.max(0, ch.target - ch.progress)} lagi` }}</span>
                    </div>
                    <div class="st-track st-track--lg">
                        <i :style="{ width: `${challengePercent(ch)}%` }"></i>
                    </div>

                    <div class="st-panel-foot challenge-foot">
                        <div class="reward-note">
                            <span class="st-icon st-icon--sm st-icon--gold"><Award :size="16" /></span>
                            <span>Hadiah <b>+{{ ch.reward_xp }} XP</b> setelah diklaim</span>
                        </div>

                        <button
                            v-if="ch.progress >= ch.target && !ch.completed"
                            type="button"
                            class="st-btn st-btn--gold"
                            :disabled="claimingId === ch.id"
                            @click="claim(ch)"
                        >
                            <Trophy :size="16" />
                            <span>{{ claimingId === ch.id ? 'Mengklaim...' : `Klaim +${ch.reward_xp} XP` }}</span>
                        </button>

                        <span v-else-if="ch.completed" class="st-badge st-badge--primary claimed-badge">
                            <CircleCheck :size="15" /> Hadiah sudah diklaim
                        </span>

                        <Link v-else href="/practice" class="st-btn st-btn--primary">
                            <span>Lanjutkan di AI Practice</span>
                            <ArrowRight :size="16" />
                        </Link>
                    </div>
                </section>
            </div>
            <div v-else class="st-empty">Belum ada tantangan untuk hari ini. Coba lagi besok!</div>
        </div>
    </AppLayout>
</template>

<style scoped>
.challenge-stack {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.challenge-head {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 20px;
}

.challenge-copy {
    flex: 1;
    min-width: 0;
}

.challenge-foot {
    flex-wrap: wrap;
    margin-top: 18px;
}

.reward-note {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: var(--st-muted);
}

.reward-note b {
    color: var(--st-gold-dark);
}

.claimed-badge {
    padding: 8px 14px;
    font-size: 13px;
}

@media (max-width: 760px) {
    .challenge-head {
        flex-wrap: wrap;
    }

    .challenge-foot .st-btn {
        width: 100%;
    }
}
</style>
