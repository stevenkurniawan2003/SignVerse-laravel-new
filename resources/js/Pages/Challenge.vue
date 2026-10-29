<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Award, CircleCheck, Star, Target, Trophy } from '@lucide/vue';
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

onMounted(async () => {
    if (challengeList.value.length === 0) {
        try {
            const res = await fetch('/api/challenges');
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
    try {
        const res = await fetch('/api/challenges/claim', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
            body: JSON.stringify({ challenge_id: ch.id }),
        });
        if (res.ok) {
            const data = await res.json();
            ch.completed = true;
            ch.progress = ch.target;
            claimStatusMessage.value = data.message || `Selamat! Bonus +${ch.reward_xp} XP berhasil diklaim.`;
            router.reload({ only: ['auth'] });
        }
    } catch {
        claimStatusMessage.value = 'Gagal mengklaim reward. Periksa koneksi jaringan.';
    } finally {
        claimingId.value = null;
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Challenge Harian" />

        <div class="info-page challenge-page">
            <header class="challenge-welcome">
                <div class="challenge-welcome-copy">
                    <div class="heading-badge">Misi Harian · SignTech Database</div>
                    <h1>Tantangan Harian & Bonus XP</h1>
                    <p>Selesaikan target latihan hari ini untuk menjaga streak dan mengumpulkan XP ekstra.</p>
                </div>
                <div class="challenge-mascot" aria-hidden="true">
                    <div class="challenge-icon-circle">
                        <Target :size="32" class="text-primary" />
                    </div>
                </div>
            </header>

            <div v-if="claimStatusMessage" class="claim-alert-box">
                <CircleCheck :size="16" />
                <span>{{ claimStatusMessage }}</span>
            </div>

            <!-- List of Challenges from Database -->
            <div class="challenge-cards-stack">
                <section
                    v-for="ch in challengeList"
                    :key="ch.id"
                    class="challenge-large card"
                >
                    <div class="challenge-card-heading">
                        <div class="challenge-big-icon">
                            <Target :size="26" />
                        </div>
                        <div>
                            <span class="eyebrow">TANTANGAN HARIAN #{{ ch.id }}</span>
                            <h2>{{ ch.title }}</h2>
                            <p>{{ ch.description }}</p>
                        </div>
                        <span class="challenge-stamp">+{{ ch.reward_xp }} XP</span>
                    </div>

                    <div class="challenge-progress-label">
                        <b>{{ ch.progress }} dari {{ ch.target }} tuntas</b>
                        <span>
                            {{ ch.progress >= ch.target ? 'Target terpenuhi!' : `Tersisa ${Math.max(0, ch.target - ch.progress)} lagi` }}
                        </span>
                    </div>

                    <div class="progress-track big-track">
                        <i :style="{ width: `${Math.min(100, Math.round(((ch.progress || 0) / (ch.target || 1)) * 100))}%` }"></i>
                    </div>

                    <div class="challenge-bottom-row">
                        <div class="challenge-reward">
                            <div class="challenge-reward-icon-box">
                                <Award :size="20" class="text-secondary" />
                            </div>
                            <div>
                                <b>Hadiah Misi</b>
                                <small>+{{ ch.reward_xp }} XP Langsung ke Database</small>
                            </div>
                        </div>

                        <button
                            v-if="ch.progress >= ch.target && !ch.completed"
                            type="button"
                            class="primary-button"
                            :disabled="claimingId === ch.id"
                            @click="claim(ch)"
                        >
                            <Trophy :size="16" />
                            <span>{{ claimingId === ch.id ? 'Mengklaim...' : `Klaim +${ch.reward_xp} XP Sekarang` }}</span>
                        </button>

                        <div v-else-if="ch.completed" class="challenge-completed-pill">
                            <CircleCheck :size="16" />
                            <span>Hadiah Berhasil Diklaim</span>
                        </div>

                        <Link v-else href="/practice" class="primary-button">
                            <span>Lanjutkan di AI Practice</span>
                            <ArrowRight :size="16" />
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.challenge-welcome {
    background: #eefaf4;
    border: 1px solid #d5f3e5;
    border-radius: 18px;
    padding: 24px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.heading-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    color: #1e7253;
    background: #d5f3e5;
    padding: 3px 10px;
    border-radius: 6px;
    margin-bottom: 8px;
}
.challenge-welcome h1 {
    font-size: 24px;
    font-weight: 800;
    color: #14221d;
    margin: 0 0 6px;
}
.challenge-welcome p {
    font-size: 13px;
    color: #52685d;
    margin: 0;
}
.challenge-icon-circle {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #ffffff;
    display: grid;
    place-items: center;
    box-shadow: 0 4px 12px rgba(52, 177, 130, 0.12);
}
.claim-alert-box {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #eefaf4;
    border: 1px solid #34B182;
    color: #1e7253;
    padding: 12px 18px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 18px;
}
.challenge-cards-stack {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.challenge-card-heading {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 20px;
}
.challenge-big-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #eefaf4;
    color: #34B182;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}
.challenge-stamp {
    margin-left: auto;
    font-size: 13px;
    font-weight: 800;
    color: #C29B40;
    background: #fdf9ef;
    border: 1px solid #f1deaf;
    padding: 4px 12px;
    border-radius: 8px;
}
.challenge-progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 6px;
}
.challenge-bottom-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #f1f5f3;
}
.challenge-reward {
    display: flex;
    align-items: center;
    gap: 10px;
}
.challenge-reward-icon-box {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #fdf9ef;
    display: grid;
    place-items: center;
}
.challenge-completed-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eefaf4;
    color: #1e7253;
    font-size: 12px;
    font-weight: 700;
    padding: 8px 14px;
    border-radius: 10px;
}
</style>
