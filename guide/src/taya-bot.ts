// Чат-бот Тая для документации Docusaurus.
//
// Подключается inline-плагином `taya-chat-bot` из `docusaurus.config.ts`:
// - скрипт виджета (UMD-сборка с CDN CDEK, глобаль `window.CdekChat`) инжектится тегом <script>;
// - `entryPoint` передаётся через globalData плагина (build-time env `TAYA_ENTRY_POINT`);
// - этот файл регистрируется как client-модуль плагина (getClientModules)
//   и выполняется только в браузере (см. guard `ExecutionEnvironment.canUseDOM`).
//
// Тема и локаль читаются из источников истины Docusaurus — атрибуты `data-theme`
// и `lang` на `<html>`. Хуки useColorMode()/useDocusaurusContext() здесь недоступны:
// они работают только внутри React-дерева, а ColorModeProvider монтируется глубже
// (внутри @theme/Layout/Provider), поэтому в client-модуле следим за атрибутами.

import ExecutionEnvironment from "@docusaurus/ExecutionEnvironment";
import globalData from "@generated/globalData";

export type TayaChatBotTheme = "dark" | "light";
export type TayaChatBotLocale = "ru" | "en";

interface CdekChatGlobal {
    renderComponentToBody?: (options: {
        entryPoint: string;
        locale?: TayaChatBotLocale;
        theme?: TayaChatBotTheme;
    }) => void;
}

declare global {
    interface Window {
        CdekChat?: CdekChatGlobal;
    }
}

function getCurrentColorMode(): TayaChatBotTheme {
    return document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light";
}

function getCurrentLocale(): TayaChatBotLocale {
    return (document.documentElement.getAttribute("lang") as TayaChatBotLocale) ?? "ru";
}

function install(): void {
    const { entryPoint } = (globalData["taya-chat-bot"]?.default ?? {}) as { entryPoint?: string };
    if (!entryPoint) {
        return;
    }

    const targetOrigin = window.location.origin;

    // Подписываемся на готовность виджета: получив "ready", отправляем актуальные
    // locale/theme/entryPoint, чтобы чат открылся в нужном виде.
    window.addEventListener("message", (event) => {
        if (event.origin !== targetOrigin || event.source !== window) {
            return;
        }

        if (event.data?.type === "chat-bot" && event.data.func === "ready") {
            window.postMessage(
                {
                    type: "chat-bot",
                    action: "sync",
                    locale: getCurrentLocale(),
                    theme: getCurrentColorMode(),
                    entryPoint,
                },
                targetOrigin,
            );
        }
    });

    let currentTheme = getCurrentColorMode();

    window.CdekChat?.renderComponentToBody?.({
        entryPoint,
        locale: getCurrentLocale(),
        theme: currentTheme,
    });

    // Синхронизация темы с переключателем light/dark в навбаре
    new MutationObserver(() => {
        currentTheme = getCurrentColorMode();
        window.postMessage({ type: "chat-bot", action: "theme-update", theme: currentTheme }, targetOrigin);
    }).observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });
}

// Client-модули Docusaurus исполняются и при SSR-прендеринге,
// поэтому вся работа с DOM — под guard.
if (ExecutionEnvironment.canUseDOM) {
    install();
}
