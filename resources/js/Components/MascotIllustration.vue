<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'student', // 'student' | 'teacher'
    },
    size: {
        type: String,
        default: 'md', // 'sm' | 'md' | 'lg'
    },
});

const dimensions = computed(() => {
    switch (props.size) {
        case 'sm':
            return { width: 110, height: 110 };
        case 'lg':
            return { width: 200, height: 200 };
        case 'md':
        default:
            return { width: 150, height: 150 };
    }
});
</script>

<template>
    <div
        class="mascot-illustration inline-flex items-center justify-center select-none"
        :style="{ width: `${dimensions.width}px`, height: `${dimensions.height}px` }"
    >
        <svg
            viewBox="0 0 200 200"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            class="w-full h-full drop-shadow-sm"
        >
            <!-- Background playful particles & decorative dots -->
            <g class="particles" opacity="0.85">
                <!-- 3x3 dot matrix (top-left) -->
                <circle cx="28" cy="78" r="2" fill="#14221d" opacity="0.3" />
                <circle cx="36" cy="78" r="2" fill="#14221d" opacity="0.3" />
                <circle cx="44" cy="78" r="2" fill="#14221d" opacity="0.3" />
                <circle cx="28" cy="86" r="2" fill="#14221d" opacity="0.3" />
                <circle cx="36" cy="86" r="2" fill="#14221d" opacity="0.3" />
                <circle cx="44" cy="86" r="2" fill="#14221d" opacity="0.3" />
                <circle cx="28" cy="94" r="2" fill="#14221d" opacity="0.3" />
                <circle cx="36" cy="94" r="2" fill="#14221d" opacity="0.3" />
                <circle cx="44" cy="94" r="2" fill="#14221d" opacity="0.3" />

                <!-- Sparkle star top-right (Gold) -->
                <path
                    d="M174 62 L176.5 54 L179 62 L187 64.5 L179 67 L176.5 75 L174 67 L166 64.5 Z"
                    fill="#C29B40"
                />

                <!-- Sparkle star bottom-left (Teal) -->
                <path
                    d="M42 120 L44 114 L46 120 L52 122 L46 124 L44 130 L42 124 L36 122 Z"
                    fill="#34B182"
                />

                <!-- Soft bubbly circles -->
                <circle cx="182" cy="132" r="4.5" fill="#C29B40" opacity="0.45" />
                <circle cx="170" cy="148" r="3" fill="#34B182" opacity="0.5" />
                <circle cx="22" cy="112" r="3.5" fill="#C29B40" opacity="0.35" />
            </g>

            <!-- STUDENT VARIANT -->
            <template v-if="variant === 'student'">
                <!-- Torso / Shirt -->
                <path
                    d="M66 144 Q100 136 134 144 L146 200 L54 200 Z"
                    fill="#E8633A"
                    stroke="#14221d"
                    stroke-width="3"
                    stroke-linejoin="round"
                />
                <!-- Shirt details / collar -->
                <polygon points="90,142 100,156 110,142" fill="#FFFFFF" stroke="#14221d" stroke-width="2.5" />
                <line x1="100" y1="156" x2="100" y2="198" stroke="#C84F2A" stroke-width="3" stroke-linecap="round" />

                <!-- Left Arm & Tablet -->
                <g class="left-arm">
                    <!-- Arm holding notebook/tablet -->
                    <path
                        d="M68 148 Q50 162 64 186"
                        fill="none"
                        stroke="#E8633A"
                        stroke-width="14"
                        stroke-linecap="round"
                    />
                    <!-- Tablet body (rotated) -->
                    <rect
                        x="56"
                        y="146"
                        width="38"
                        height="48"
                        rx="5"
                        transform="rotate(-14 75 170)"
                        fill="#34B182"
                        stroke="#14221d"
                        stroke-width="3"
                    />
                    <!-- Tablet Screen -->
                    <rect
                        x="60"
                        y="150"
                        width="30"
                        height="40"
                        rx="3"
                        transform="rotate(-14 75 170)"
                        fill="#EBF8F2"
                    />
                    <!-- Screen content lines (representing sign language alphabet) -->
                    <line x1="68" y1="160" x2="84" y2="156" stroke="#34B182" stroke-width="2.5" stroke-linecap="round" />
                    <line x1="69" y1="166" x2="81" y2="163" stroke="#8FD4B9" stroke-width="2" stroke-linecap="round" />
                    <line x1="70" y1="172" x2="85" y2="168" stroke="#8FD4B9" stroke-width="2" stroke-linecap="round" />
                    <!-- Hand holding tablet -->
                    <path
                        d="M78 178 Q84 172 88 176 Q86 182 80 183 Z"
                        fill="#FCD5B5"
                        stroke="#14221d"
                        stroke-width="2.5"
                    />
                </g>

                <!-- Right Arm Pointing Upward / Sign Hand -->
                <g class="right-arm">
                    <!-- Upper & forearm -->
                    <path
                        d="M132 148 Q152 142 162 118"
                        fill="none"
                        stroke="#E8633A"
                        stroke-width="14"
                        stroke-linecap="round"
                    />
                    <!-- Sleeve cuff line -->
                    <line x1="156" y1="126" x2="165" y2="119" stroke="#14221d" stroke-width="3" stroke-linecap="round" />
                    <!-- Palm -->
                    <circle cx="163" cy="112" r="8.5" fill="#FCD5B5" stroke="#14221d" stroke-width="2.5" />
                    <!-- Folded fingers -->
                    <path d="M158 114 Q161 118 167 117" stroke="#14221d" stroke-width="2" fill="none" stroke-linecap="round" />
                    <path d="M157 110 Q160 114 166 113" stroke="#14221d" stroke-width="2" fill="none" stroke-linecap="round" />
                    <!-- Thumb pointing slightly out -->
                    <path d="M156 107 Q150 109 154 115" fill="#FCD5B5" stroke="#14221d" stroke-width="2.5" stroke-linecap="round" />
                    <!-- Index finger pointing UP proudly -->
                    <path
                        d="M161 110 L166 86 Q168 81 173 85 L168 110 Z"
                        fill="#FCD5B5"
                        stroke="#14221d"
                        stroke-width="2.5"
                        stroke-linejoin="round"
                    />
                </g>

                <!-- Neck -->
                <rect x="94" y="130" width="12" height="14" fill="#E8BD9A" stroke="#14221d" stroke-width="2.5" />

                <!-- Head Base -->
                <ellipse cx="100" cy="98" rx="35" ry="37" fill="#FCD5B5" stroke="#14221d" stroke-width="3" />

                <!-- Hair (Under cap) -->
                <path
                    d="M66 84 Q100 68 134 84 Q138 98 135 106 Q130 96 124 93 Q110 88 96 90 Q80 92 68 100 Q65 92 66 84 Z"
                    fill="#271C19"
                />

                <!-- Cheerful Face Features -->
                <!-- Blush spots -->
                <ellipse cx="78" cy="105" rx="5.5" ry="3.5" fill="#FF9A9A" opacity="0.65" />
                <ellipse cx="122" cy="105" rx="5.5" ry="3.5" fill="#FF9A9A" opacity="0.65" />

                <!-- Happy arc eyes -->
                <path d="M81 97 Q87 90 93 97" fill="none" stroke="#14221d" stroke-width="3.2" stroke-linecap="round" />
                <path d="M107 97 Q113 90 119 97" fill="none" stroke="#14221d" stroke-width="3.2" stroke-linecap="round" />

                <!-- Cute eyebrows -->
                <path d="M81 89 Q87 85 92 88" fill="none" stroke="#14221d" stroke-width="2.5" stroke-linecap="round" />
                <path d="M108 88 Q113 85 119 89" fill="none" stroke="#14221d" stroke-width="2.5" stroke-linecap="round" />

                <!-- Big friendly smile -->
                <path
                    d="M88 107 Q100 124 112 107"
                    fill="#AC3434"
                    stroke="#14221d"
                    stroke-width="2.8"
                    stroke-linejoin="round"
                />
                <!-- Teeth highlight -->
                <path d="M91 108 Q100 114 109 108 Z" fill="#FFFFFF" />

                <!-- Graduation Cap (Mortarboard) -->
                <!-- Skullcap underneath -->
                <path
                    d="M66 60 Q100 74 134 60 L130 74 Q100 90 70 74 Z"
                    fill="#0F2B24"
                    stroke="#081814"
                    stroke-width="2.5"
                />
                <!-- Diamond Cap top -->
                <polygon
                    points="100,28 164,52 100,74 36,52"
                    fill="#153E35"
                    stroke="#091A16"
                    stroke-width="3.2"
                    stroke-linejoin="round"
                />
                <!-- Inner top highlight -->
                <polygon points="100,32 156,52 100,70 44,52" fill="#1C4F44" opacity="0.6" />
                <!-- Center Gold Button -->
                <circle cx="100" cy="52" r="5" fill="#C29B40" stroke="#091A16" stroke-width="2" />
                <!-- Golden Tassel swaying to the left -->
                <path
                    d="M100 52 Q72 54 62 80"
                    fill="none"
                    stroke="#C29B40"
                    stroke-width="3"
                    stroke-linecap="round"
                />
                <!-- Tassel brush -->
                <polygon
                    points="57,78 66,78 64,94 59,94"
                    fill="#C29B40"
                    stroke="#947427"
                    stroke-width="1.5"
                    stroke-linejoin="round"
                />
            </template>

            <!-- TEACHER VARIANT -->
            <template v-else>
                <!-- Torso / Smart Blazer -->
                <path
                    d="M64 144 Q100 136 136 144 L148 200 L52 200 Z"
                    fill="#1A4A40"
                    stroke="#14221d"
                    stroke-width="3"
                    stroke-linejoin="round"
                />
                <!-- Inner Shirt & Scarf / Tie -->
                <polygon points="88,142 100,166 112,142" fill="#FFFFFF" stroke="#14221d" stroke-width="2" />
                <polygon points="96,150 100,172 104,150" fill="#C29B40" />

                <!-- Left Arm holding clipboard / lesson plan -->
                <g class="teacher-left-arm">
                    <path
                        d="M66 148 Q48 164 62 188"
                        fill="none"
                        stroke="#1A4A40"
                        stroke-width="14"
                        stroke-linecap="round"
                    />
                    <!-- Clipboard -->
                    <rect
                        x="54"
                        y="148"
                        width="38"
                        height="48"
                        rx="4"
                        transform="rotate(-10 73 172)"
                        fill="#E8A752"
                        stroke="#14221d"
                        stroke-width="3"
                    />
                    <!-- Paper sheet -->
                    <rect
                        x="57"
                        y="152"
                        width="32"
                        height="40"
                        rx="2"
                        transform="rotate(-10 73 172)"
                        fill="#FFFFFF"
                    />
                    <line x1="64" y1="162" x2="82" y2="159" stroke="#34B182" stroke-width="2.5" stroke-linecap="round" />
                    <line x1="65" y1="168" x2="80" y2="165" stroke="#60766E" stroke-width="2" stroke-linecap="round" />
                    <!-- Hand holding clipboard -->
                    <circle cx="82" cy="176" r="6" fill="#FCD5B5" stroke="#14221d" stroke-width="2" />
                </g>

                <!-- Right Arm making friendly thumbs up / greeting -->
                <g class="teacher-right-arm">
                    <path
                        d="M134 148 Q154 142 164 120"
                        fill="none"
                        stroke="#1A4A40"
                        stroke-width="14"
                        stroke-linecap="round"
                    />
                    <!-- Hand palm with Sign Language 'B' or Open Palm -->
                    <circle cx="164" cy="112" r="9" fill="#FCD5B5" stroke="#14221d" stroke-width="2.5" />
                    <!-- 4 open fingers up (Sign 'B') -->
                    <rect x="160" y="86" width="12" height="26" rx="6" fill="#FCD5B5" stroke="#14221d" stroke-width="2.5" />
                    <line x1="163" y1="92" x2="163" y2="108" stroke="#14221d" stroke-width="1.5" />
                    <line x1="166" y1="92" x2="166" y2="108" stroke="#14221d" stroke-width="1.5" />
                    <line x1="169" y1="92" x2="169" y2="108" stroke="#14221d" stroke-width="1.5" />
                </g>

                <!-- Neck -->
                <rect x="94" y="130" width="12" height="14" fill="#E8BD9A" stroke="#14221d" stroke-width="2.5" />

                <!-- Head Base -->
                <ellipse cx="100" cy="98" rx="35" ry="37" fill="#FCD5B5" stroke="#14221d" stroke-width="3" />

                <!-- Teacher Hair (Neat bun / styled) -->
                <circle cx="100" cy="56" r="16" fill="#2E1C14" stroke="#14221d" stroke-width="2.5" />
                <path
                    d="M64 86 Q100 66 136 86 Q140 102 136 114 Q130 96 122 92 Q100 86 78 92 Q70 96 64 114 Z"
                    fill="#2E1C14"
                />

                <!-- Smart Glasses -->
                <rect x="74" y="93" width="22" height="15" rx="5" fill="#E8F5F1" fill-opacity="0.4" stroke="#14221d" stroke-width="2.5" />
                <rect x="104" y="93" width="22" height="15" rx="5" fill="#E8F5F1" fill-opacity="0.4" stroke="#14221d" stroke-width="2.5" />
                <line x1="96" y1="99" x2="104" y2="99" stroke="#14221d" stroke-width="2.5" />

                <!-- Friendly Eyes behind glasses -->
                <circle cx="85" cy="100" r="3" fill="#14221d" />
                <circle cx="86" cy="99" r="1" fill="#FFFFFF" />
                <circle cx="115" cy="100" r="3" fill="#14221d" />
                <circle cx="116" cy="99" r="1" fill="#FFFFFF" />

                <!-- Blush -->
                <ellipse cx="78" cy="109" rx="5" ry="3" fill="#FF9A9A" opacity="0.6" />
                <ellipse cx="122" cy="109" rx="5" ry="3" fill="#FF9A9A" opacity="0.6" />

                <!-- Smile -->
                <path
                    d="M89 112 Q100 126 111 112"
                    fill="#AC3434"
                    stroke="#14221d"
                    stroke-width="2.5"
                    stroke-linejoin="round"
                />
                <path d="M92 113 Q100 118 108 113 Z" fill="#FFFFFF" />
            </template>
        </svg>
    </div>
</template>

<style scoped>
.mascot-illustration {
    transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.mascot-illustration:hover {
    transform: scale(1.04) translateY(-3px);
}
</style>
