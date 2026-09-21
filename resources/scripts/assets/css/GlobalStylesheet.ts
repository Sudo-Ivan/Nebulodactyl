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

    html[data-theme='light'], html[data-theme='light'] body, html[data-theme='light'] #app {
        color: #18181b;
    }

    body {
        /* near-black canvas with a faint white glow */
        background-color: #0a0a0b;
        background-image:
            radial-gradient(24rem 12rem at 88% -15%, rgb(255 255 255 / 0.10), transparent 70%),
            radial-gradient(40% 30% at 15% 100%, rgb(255 255 255 / 0.04), transparent 70%);
        background-attachment: fixed;
    }

    html[data-theme='light'] body {
        background-color: #fafafa;
        background-image:
            radial-gradient(24rem 12rem at 88% -15%, rgb(24 24 27 / 0.06), transparent 70%),
            radial-gradient(40% 30% at 15% 100%, rgb(24 24 27 / 0.03), transparent 70%);
    }

    /* stars belong to the dark sky */
    html[data-theme='light'] body::before,
    html[data-theme='light'] body::after {
        display: none;
    }

    /* Starfield layers: two sheets of fixed dots twinkling out of phase */
    body::before,
    body::after {
        content: '';
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 0;
    }

    body::before {
        background-image:
            radial-gradient(1px 1px at 8% 22%, rgb(255 255 255 / 0.90) 50%, transparent 51%),
            radial-gradient(1px 1px at 22% 68%, rgb(255 255 255 / 0.55) 50%, transparent 51%),
            radial-gradient(1px 1px at 34% 12%, rgb(255 255 255 / 0.70) 50%, transparent 51%),
            radial-gradient(1px 1px at 47% 82%, rgb(255 255 255 / 0.45) 50%, transparent 51%),
            radial-gradient(1px 1px at 58% 30%, rgb(255 255 255 / 0.60) 50%, transparent 51%),
            radial-gradient(1px 1px at 66% 60%, rgb(255 255 255 / 0.45) 50%, transparent 51%),
            radial-gradient(1px 1px at 74% 8%, rgb(255 255 255 / 0.70) 50%, transparent 51%),
            radial-gradient(1px 1px at 83% 44%, rgb(255 255 255 / 0.55) 50%, transparent 51%),
            radial-gradient(1px 1px at 91% 74%, rgb(255 255 255 / 0.80) 50%, transparent 51%);
        animation: twinkle 4s ease-in-out infinite;
    }

    body::after {
        background-image:
            radial-gradient(1px 1px at 15% 88%, rgb(255 255 255 / 0.50) 50%, transparent 51%),
            radial-gradient(1px 1px at 52% 52%, rgb(255 255 255 / 0.65) 50%, transparent 51%),
            radial-gradient(1px 1px at 96% 28%, rgb(255 255 255 / 0.55) 50%, transparent 51%),
            radial-gradient(1px 1px at 40% 40%, rgb(255 255 255 / 0.40) 50%, transparent 51%),
            radial-gradient(1px 1px at 70% 90%, rgb(255 255 255 / 0.50) 50%, transparent 51%),
            radial-gradient(1px 1px at 5% 55%, rgb(255 255 255 / 0.60) 50%, transparent 51%),
            radial-gradient(1px 1px at 88% 90%, rgb(255 255 255 / 0.45) 50%, transparent 51%);
        animation: twinkle 6.5s ease-in-out infinite reverse;
    }

    #app {
        z-index: 1;
    }

    @keyframes twinkle {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.35; }
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
        -webkit-box-shadow: inset 0 0 0 3px hsl(240deg 5% 26%);
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
        background: radial-gradient(124.75% 124.75% at 50.01% -10.55%, rgba(90, 90, 98, 0.3) 0%, rgb(16, 16, 19, 0.2) 100%);
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
            background: #1f1f24dd;
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
            background: #1f1f24dd;
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
        color: color-mix(in srgb, var(--color-cream-50) 33%, transparent) !important;
    }

    html[data-theme='light'] input::placeholder {
        color: #18181b66 !important;
    }

    html[data-theme='light'] [cmdk-dialog] {
        background: #18181b33;
    }

    html[data-theme='light'] [cmdk-root] {
        background: rgb(255 255 255 / 0.92);
        box-shadow: rgba(0, 0, 0, 0.18) 0px 16px 70px;
    }

    html[data-theme='light'] [cmdk-input] {
        color: #18181b;
        border-bottom-color: #18181b22;

        &::placeholder {
            color: #a1a1aa;
        }
    }

    html[data-theme='light'] [cmdk-item] {
        &[data-selected='true'] {
            background: #e9e9ecdd;

            svg {
                color: #18181b;
            }
        }

        &[data-disabled='true'], &[data-disabled='true'] svg {
            color: #a1a1aa;
        }

        &:active {
            background: #e9e9ecdd;
        }

        svg {
            color: #3f3f46;
        }
    }
`;
