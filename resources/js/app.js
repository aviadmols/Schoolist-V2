import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import '../css/app.css';

const APP_TITLE = 'Schoolist V2';

/**
 * Compute the document title for Inertia pages.
 *
 * @param {string} pageTitle
 * @returns {string}
 */
function computeInertiaTitle(pageTitle) {
  return pageTitle ? `${pageTitle} - ${APP_TITLE}` : APP_TITLE;
}

/**
 * Resolve an Inertia page component by name.
 *
 * @param {string} pageName
 * @returns {Promise<import('vue').Component>}
 */
function resolveInertiaPageComponent(pageName) {
  return resolvePageComponent(
    `./Pages/${pageName}.vue`,
    import.meta.glob('./Pages/**/*.vue')
  );
}

/**
 * Mount the Inertia Vue application.
 *
 * @param {{ el: Element, App: import('vue').Component, props: Record<string, unknown>, plugin: unknown }} inertiaArgs
 * @returns {import('vue').App}
 */
function mountInertiaVueApp(inertiaArgs) {
  const { el, App, props, plugin } = inertiaArgs;

  /**
   * Render the current Inertia root component.
   *
   * @returns {import('vue').VNode}
   */
  function renderInertiaRoot() {
    return h(App, props);
  }

  return createApp({ render: renderInertiaRoot })
    .use(plugin)
    .mount(el);
}

createInertiaApp({
  title: computeInertiaTitle,
  resolve: resolveInertiaPageComponent,
  setup: mountInertiaVueApp,
});

