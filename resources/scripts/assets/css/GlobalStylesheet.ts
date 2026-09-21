import { createGlobalStyle } from 'styled-components';

export default createGlobalStyle`
    // * {
    //     min-width: 0
    // }

    html, body, #app {
        position: relative;
        height: 100%;
        width: 100%;
        overflow: hidden;
        font-family: "Plus Jakarta Sans", sans-serif;
        --tw-text-opacity: 1;
        color: rgb(255 255 255 / var(--tw-text-opacity));
    }

    body {
        /* Deep-space base with a faint nebula glow and starfield */
        background-color: #070510;
        background-image:
            radial-gradient(60% 45% at 18% 0%, rgb(124 58 237 / 0.14) 0%, transparent 70%),
            radial-gradient(50% 40% at 85% 15%, rgb(34 211 238 / 0.08) 0%, transparent 70%),
            radial-gradient(45% 35% at 70% 90%, rgb(109 40 217 / 0.10) 0%, transparent 70%),
            radial-gradient(1px 1px at 8% 22%, rgb(255 255 255 / 0.55) 50%, transparent 51%),
            radial-gradient(1px 1px at 22% 68%, rgb(255 255 255 / 0.40) 50%, transparent 51%),
            radial-gradient(1px 1px at 34% 12%, rgb(255 255 255 / 0.50) 50%, transparent 51%),
            radial-gradient(1px 1px at 47% 82%, rgb(255 255 255 / 0.35) 50%, transparent 51%),
            radial-gradient(1px 1px at 58% 30%, rgb(255 255 255 / 0.45) 50%, transparent 51%),
            radial-gradient(1px 1px at 66% 60%, rgb(255 255 255 / 0.35) 50%, transparent 51%),
            radial-gradient(1px 1px at 74% 8%, rgb(255 255 255 / 0.50) 50%, transparent 51%),
            radial-gradient(1px 1px at 83% 44%, rgb(255 255 255 / 0.40) 50%, transparent 51%),
            radial-gradient(1px 1px at 91% 74%, rgb(255 255 255 / 0.55) 50%, transparent 51%),
            radial-gradient(1px 1px at 15% 88%, rgb(196 181 253 / 0.50) 50%, transparent 51%),
            radial-gradient(1px 1px at 52% 52%, rgb(165 243 252 / 0.45) 50%, transparent 51%),
            radial-gradient(1px 1px at 96% 28%, rgb(196 181 253 / 0.45) 50%, transparent 51%);
        background-attachment: fixed;
    }

    button {
        user-select: none;
    }

    form {
        margin: 0;
    }

    textarea, select, input, button, button:focus, button:focus-visible {
        outline: none;
    }

    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none !important;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield !important;
    }

    /* Scroll Bar Style */
    ::-webkit-scrollbar {
        background: none;
        width: 10px;
        height: 16px;
    }

    ::-webkit-scrollbar-thumb {
        border: solid 0 rgb(0 0 0 / 0%);
        border-right-width: 3px;
        border-left-width: 3px;
        -webkit-border-radius: 9px 4px;
        -webkit-box-shadow: inset 0 0 0 3px hsl(258deg 30% 35%);
    }

    ::-webkit-scrollbar-track-piece {
        margin: 4px 0;
    }

    ::-webkit-scrollbar-thumb:horizontal {
        border-right-width: 0;
        border-left-width: 0;
        border-top-width: 4px;
        border-bottom-width: 4px;
        -webkit-border-radius: 4px 9px;
    }

    ::-webkit-scrollbar-corner {
        background: transparent;
    }

    @keyframes list-anim {
        0% {
            transform: translateY(-22px) scale(0.98);
            opacity: 0;
        }

        100% {
            transform: none;
            opacity: 1;
        }
    }

    .skeleton-anim-2 {
        animation: list-anim 1.5s both;
        will-change: transform;
    }

    [cmdk-dialog] {
        position: fixed;
        width: 100%;
        height: 100%;
        inset: 0;
        z-index: 9999;
        display: flex;
        align-items: flex-start;
        -webkit-box-pack: center;
        justify-content: center;
        padding: calc(13vh - -0.19px) 16px 16px;
        background: #00000055;
    }

    [cmdk-root] {
        max-width: 640px;
        width: 100%;
        border-radius: 16px;
        overflow: hidden;
        padding: 0;
        outline: none;
        background: radial-gradient(124.75% 124.75% at 50.01% -10.55%, rgba(76, 61, 130, 0.3) 0%, rgb(23, 18, 40, 0.2) 100%);
        backdrop-filter: blur(20px);
        box-shadow: rgba(0, 0, 0, 0.5) 0px 16px 70px;
        position: relative;
        display: flex;
        flex-direction: column;
        flex-shrink: 1;
        -webkit-box-flex: 1;
        flex-grow: 1;
        min-width: min-content;
        will-change: transform;
        transform-origin: center center;
    }

    [cmdk-input] {
        border: none;
        width: 100%;
        font-size: 18px;
        font-weight: bold;
        padding: 20px;
        outline: none;
        background: transparent;
        border-bottom: 1px solid #3a3a3a44;
        color: #eee;
        border-radius: 0;
        caret-color: var(--color-brand);
        margin: 0;

        &::placeholder {
            color: #444;
        }
    }

    [cmdk-item] {
        content-visibility: auto;
        font-weight: bold;
        cursor: pointer;
        height: 48px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0 16px;
        border-radius: 12px;
        user-select: none;
        will-change: background, color;
        transition: all 150ms ease;
        transition-property: none;
        position: relative;
        margin-left: 8px;
        margin-right: 8px;

        &[data-selected='true'] {
            background: #332c4ddd;
            box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 12px;

            svg {
                color: #fff;
            }

            /* &:after {
                content: '';
                position: absolute;
                left: 0;
                z-index: 123;
                width: 3px;
                height: 100%;
                background: var(--color-brand);
            } */
        }

        &[data-disabled='true'] {
            color: #444;
            cursor: not-allowed;
        }

        &[data-disabled='true'] svg {
            color: #444;
        }

        &:active {
            transition-property: background;
            background: #332c4ddd;
        }

        svg {
            width: 16px;
            height: 16px;
            color: #ddd;
        }
    }

    [cmdk-list] {
        height: min(300px, var(--cmdk-list-height));
        max-height: 400px;
        overflow: auto;
        overscroll-behavior: contain;
        transition: 100ms ease;
        transition-property: height;
    }

    [cmdk-list] {
        scroll-padding-block-start: 8px;
        scroll-padding-block-end: 8px;
    }

    [cmdk-group-heading] {
        user-select: none;
        font-size: 12px;
        color: var(--gray11);
        padding: 0 16px;
        margin-top: 8px;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
    }

    [cmdk-empty] {
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 64px;
        white-space: pre-wrap;
        color: var(--gray11);
    }

    input::placeholder {
        color: #ffffff55 !important;
    }
`;
