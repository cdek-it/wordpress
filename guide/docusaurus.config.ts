import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

const config: Config = {
  title: 'CDEKDelivery',
  tagline: 'Wooocommerce + CDEK = ❤️',
  favicon: 'img/favicon.ico',

  // Set the production url of your site here
  url: 'https://cdek-it.github.io',
  // Set the /<baseUrl>/ pathname under which your site is served
  // For GitHub pages deployment, it is often '/<projectName>/'
  baseUrl: '/wordpress/',

  // GitHub pages deployment config.
  // If you aren't using GitHub pages, you don't need these.
  organizationName: 'cdek-it', // Usually your GitHub org/user name.
  projectName: 'wordpress', // Usually your repo name.

  onBrokenLinks: 'throw',
  onBrokenMarkdownLinks: 'warn',

  // Even if you don't use internationalization, you can use this field to set
  // useful metadata like html lang. For example, if your site is Chinese, you
  // may want to replace "en" with "zh-Hans".
  i18n: {
    defaultLocale: 'ru',
    locales: ['ru', 'en'],
  },

  plugins: [
      [
        '@docusaurus/plugin-ideal-image',
        {},
      ]
  ],

  presets: [
    [
      'classic',
      {
        docs: {
          routeBasePath: '/',
          sidebarPath: './sidebars.ts',
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],

  themeConfig: {
    // Replace with your project's social card
    image: 'img/banner.png',
    navbar: {
      hideOnScroll: true,
      title: 'Delivery',
      logo: {
        alt: 'Логотип CDEKDelivery',
        src: 'img/logo.svg',
      },
      items: [
        {
          type: 'localeDropdown',
          position: 'right',
        },
        {
          href: 'https://github.com/cdek-it/wordpress',
          label: 'GitHub',
          position: 'right',
        },
      ],
    },
    footer: {
      style: 'light',
      links: [
        {
          title: 'О плагине',
          items: [
            {
              label: 'Информация о версиях',
              to: 'https://translate.wordpress.org/projects/wp-plugins/cdekdelivery/',
            },
            {
              label: 'Страница плагина',
              href: 'https://wordpress.org/plugins/cdekdelivery/',
            },
          ],
        },
        {
          title: 'Официальные ресурсы',
          items: [
            {
              label: 'Заключить договор',
              href: 'https://www.cdek.ru/ru/contract/',
            },
            {
              label: 'Личный кабинет',
              href: 'https://api-docs.cdek.ru/29923741.html',
            },
          ],
        },
        {
          title: 'Полезные ссылки',
          items: [
            {
              label: 'Рассчитать стоимость доставки',
              href: 'https://www.cdek.ru/ru/',
            },
            {
              label: 'Техническая поддержка',
              href: 'mailto:integrator@cdek.ru',
            },
          ],
        },
      ],
      copyright: `Copyright © 2024 ООО "СДЭК ДИДЖИТАЛ"`,
    },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
      defaultLanguage: 'php',
      magicComments: [],
      additionalLanguages: [],
    },
  } satisfies Preset.ThemeConfig,
};

// noinspection JSUnusedGlobalSymbols
export default config;
