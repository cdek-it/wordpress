import path from "path";
import { themes as prismThemes } from "prism-react-renderer";
import type { Config, Plugin } from "@docusaurus/types";
import type * as Preset from "@docusaurus/preset-classic";

// Чат-бот Тая: скрипт виджета (UMD-сборка с CDN CDEK, глобаль window.CdekChat)
// инжектится тегом <script> в <head>, а client-модуль src/taya-bot.ts
// инициализирует виджет и синхронизирует его тему/локаль с сайтом.
const TAYA_CHAT_BOT_SCRIPT_URL = "https://public-static.cdek.ru/chat-bot/releases/latest/main.js";

function tayaChatBot(): Plugin {
    // entryPoint чат-бота (строковый идентификатор витрины)
    // задаётся через env при сборке (см. .github/workflows/deploy-pages.yml).
    const entryPoint = process.env.TAYA_ENTRY_POINT ?? "";

    return {
        name: "taya-chat-bot",
        async contentLoaded({ actions }) {
            actions.setGlobalData({ entryPoint });
        },
        getClientModules() {
            return [path.resolve(__dirname, "src/taya-bot.ts")];
        },
        injectHtmlTags() {
            // Без entryPoint виджет не инициализируется — не подключаем и скрипт.
            if (!entryPoint) {
                return {};
            }

            return {
                headTags: [
                    {
                        tagName: "script",
                        attributes: { src: TAYA_CHAT_BOT_SCRIPT_URL },
                    },
                ],
            };
        },
    };
}

const config: Config = {
    title: "CDEKDelivery",
    tagline: "Wooocommerce + CDEK = ❤️",
    favicon: "img/favicon.ico",

    // Set the production url of your site here
    url: "https://cdek-it.github.io",
    // Set the /<baseUrl>/ pathname under which your site is served
    // For GitHub pages deployment, it is often '/<projectName>/'
    baseUrl: "/wordpress/",

    // GitHub pages deployment config.
    // If you aren't using GitHub pages, you don't need these.
    organizationName: "cdek-it", // Usually your GitHub org/user name.
    projectName: "wordpress", // Usually your repo name.

    onBrokenLinks: "throw",
    onBrokenMarkdownLinks: "warn",

    // Even if you don't use internationalization, you can use this field to set
    // useful metadata like html lang. For example, if your site is Chinese, you
    // may want to replace "en" with "zh-Hans".
    i18n: {
        defaultLocale: "ru",
        locales: ["ru", "en"],
    },

    plugins: [
        ["@docusaurus/plugin-ideal-image", {}],
        [
            "docusaurus-plugin-yandex-metrica",
            {
                counterID: "98844577",
                webvisor: true,
            },
        ],
        tayaChatBot,
    ],

    presets: [
        [
            "classic",
            {
                docs: {
                    routeBasePath: "/",
                    sidebarPath: "./sidebars.json",
                },
                blog: false,
                theme: {
                    customCss: "./src/css/custom.css",
                },
            } satisfies Preset.Options,
        ],
    ],

    themeConfig: {
        // Replace with your project's social card
        image: "img/banner.png",
        algolia: {
            appId: "X22O3RCFN8",
            apiKey: "66bf4ae3650f1b4b010e61f4d442186f",
            indexName: "wordpress",
        },
        navbar: {
            hideOnScroll: true,
            title: "Delivery",
            logo: {
                alt: "Логотип CDEKDelivery",
                src: "img/logo.svg",
            },
            items: [
                {
                    type: "localeDropdown",
                    position: "right",
                },
                {
                    href: "https://github.com/cdek-it/wordpress",
                    label: "GitHub",
                    position: "right",
                },
            ],
        },
        footer: {
            style: "light",
            links: [
                {
                    title: "О плагине",
                    items: [
                        {
                            label: "История изменений",
                            href: "https://github.com/cdek-it/wordpress/releases",
                        },
                        {
                            label: "Страница плагина",
                            href: "https://wordpress.org/plugins/cdekdelivery/",
                        },
                    ],
                },
                {
                    title: "Официальные ресурсы",
                    items: [
                        {
                            label: "Заключить договор",
                            href: "https://www.cdek.ru/ru/contract/",
                        },
                        {
                            label: "Личный кабинет",
                            href: "https://lk.cdek.ru/",
                        },
                    ],
                },
                {
                    title: "Полезные ссылки",
                    items: [
                        {
                            label: "Рассчитать стоимость доставки",
                            href: "https://www.cdek.ru/ru/",
                        },
                        {
                            label: "Техническая поддержка",
                            href: "mailto:integrator@cdek.ru",
                        },
                    ],
                },
            ],
            copyright: `Copyright © 2025 ООО "СДЭК ДИДЖИТАЛ"`,
        },
        prism: {
            theme: prismThemes.github,
            darkTheme: prismThemes.dracula,
            defaultLanguage: "php",
            magicComments: [],
            additionalLanguages: [],
        },
    } satisfies Preset.ThemeConfig,
};

// noinspection JSUnusedGlobalSymbols
export default config;
