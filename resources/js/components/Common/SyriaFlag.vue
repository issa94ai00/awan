<template>
    <!-- Current Syrian flag: green / white / black with three red stars -->
    <svg class="syria-flag" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Syria">
        <rect width="30" height="20" fill="#000000" />
        <rect width="30" height="13.334" fill="#ffffff" />
        <rect width="30" height="6.667" fill="#007a3d" />
        <g fill="#ce1126">
            <polygon points="8.50,7.30 9.11,9.17 11.07,9.17 9.48,10.32 10.09,12.18 8.50,11.03 6.91,12.18 7.52,10.32 5.93,9.17 7.89,9.17" />
            <polygon points="15.00,7.30 15.61,9.17 17.57,9.17 15.98,10.32 16.59,12.18 15.00,11.03 13.41,12.18 14.02,10.32 12.43,9.17 14.39,9.17" />
            <polygon points="21.50,7.30 22.11,9.17 24.07,9.17 22.48,10.32 23.09,12.18 21.50,11.03 19.91,12.18 20.52,10.32 18.93,9.17 20.89,9.17" />
        </g>
    </svg>
</template>

<style scoped>
.syria-flag {
    display: inline-block;
    width: 1.35em;
    height: 0.9em;
    vertical-align: -0.1em;
    border-radius: 2px;
    box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
}
</style>
