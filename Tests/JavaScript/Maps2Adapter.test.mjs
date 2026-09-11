import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import test from 'node:test';

import {
  Maps2InfoWindowAdapter,
  claimAdapterElements,
  normalizeBoolean,
} from '../../Resources/Public/JavaScript/Maps2Adapter.js';

test('package depends on maps2 and the generic BayernAtlas module', () => {
  const packageDirectory = new URL('../../', import.meta.url);
  const composer = JSON.parse(
    readFileSync(new URL('composer.json', packageDirectory), 'utf8'),
  );

  assert.equal(composer.name, 'elementareteilchen/maps2-bayernatlas');
  assert.equal(composer.require['elementareteilchen/bayernatlas-fluid'], '^0.1.1');
  assert.equal(composer.require['jweiland/maps2'], '^12.2 || ^13.1');
  assert.equal(composer.require['typo3/cms-core'], '^13.4 || ^14.3');
  assert.equal(existsSync(new URL('ext_emconf.php', packageDirectory)), false);
});

test('PHP workflow tests the minimum supported component release', (context) => {
  const packageDirectory = new URL('../../', import.meta.url);
  const workflowFile = new URL('.github/workflows/tests.yml', packageDirectory);

  if (!existsSync(workflowFile)) {
    context.skip('Workflow files are intentionally excluded from release archives.');

    return;
  }

  const composer = JSON.parse(
    readFileSync(new URL('composer.json', packageDirectory), 'utf8'),
  );
  const workflow = readFileSync(
    workflowFile,
    'utf8',
  );
  const componentVersion = composer.require['elementareteilchen/bayernatlas-fluid'].slice(1);

  assert.ok(workflow.includes(`ref: v${componentVersion}`));
  assert.ok(workflow.includes(`"elementareteilchen/bayernatlas-fluid":"${componentVersion}"`));
});

test('published adapter registers no development demo services', () => {
  const packageDirectory = new URL('../../', import.meta.url);
  const services = readFileSync(
    new URL('../../Configuration/Services.yaml', import.meta.url),
    'utf8',
  );
  assert.equal(existsSync(new URL('Classes/Command/CreateDemoCommand.php', packageDirectory)), false);
  assert.doesNotMatch(services, /demo/i);
  assert.equal(composerHasDemoDependency(packageDirectory), false);
});

function composerHasDemoDependency(packageDirectory) {
  const composer = JSON.parse(readFileSync(new URL('composer.json', packageDirectory), 'utf8'));

  return Object.keys(composer.require).some((name) => name.includes('demo'));
}

test('normalizes Fluid boolean values', () => {
  assert.equal(normalizeBoolean(true), true);
  assert.equal(normalizeBoolean('1'), true);
  assert.equal(normalizeBoolean('0'), false);
  assert.equal(normalizeBoolean('false'), false);
  assert.equal(normalizeBoolean('', true), true);
});

test('claims each maps2 adapter only once', () => {
  const first = { dataset: {} };
  const second = { dataset: {} };
  const root = {
    querySelectorAll(selector) {
      assert.equal(selector, '.maps2-bayernatlas-adapter');

      return [first, second];
    },
  };

  assert.deepEqual(claimAdapterElements(root), [first, second]);
  assert.deepEqual(claimAdapterElements(root), []);
});

test('loads the existing maps2 AJAX info window after item selection', async () => {
  const originalFetch = globalThis.fetch;
  let registeredEvent = null;
  let request = null;
  const calls = [];
  const element = {
    dataset: {
      ajaxUrl: '/maps2-info',
      infoWindow: '1',
    },
    addEventListener(name) {
      registeredEvent = name;
    },
  };

  globalThis.fetch = async (url, options) => {
    request = { url, options };

    return {
      ok: true,
      async json() {
        return { content: '<h3>Maximilianeum</h3>' };
      },
    };
  };

  try {
    const adapter = new Maps2InfoWindowAdapter(element);
    await adapter.handleSelection({
      detail: {
        item: { id: 67 },
        showLoading: () => calls.push('loading'),
        showInfo: (content) => calls.push(content),
        showError: (message) => calls.push(message),
      },
      preventDefault: () => calls.push('prevented'),
    });
  } finally {
    globalThis.fetch = originalFetch;
  }

  assert.equal(registeredEvent, 'bayernatlas:item-select');
  assert.equal(request.url, '/maps2-info');
  assert.equal(request.options.method, 'POST');
  assert.equal(request.options.headers['ext-maps2'], 'infoWindowContent');
  assert.equal(request.options.body, JSON.stringify({ poiCollection: 67 }));
  assert.deepEqual(calls, ['prevented', 'loading', '<h3>Maximilianeum</h3>']);
});

test('hides internal maps2 errors returned with HTTP 200', async () => {
  const originalFetch = globalThis.fetch;
  const originalConsoleError = console.error;
  const calls = [];
  const consoleErrors = [];
  const adapter = new Maps2InfoWindowAdapter({
    dataset: {
      ajaxUrl: '/maps2-info',
      infoWindow: '1',
      errorMessage: 'Translated request error.',
    },
    addEventListener() {},
  });

  globalThis.fetch = async () => ({
    ok: true,
    async json() {
      return { error: 'Missing site settings.' };
    },
  });
  console.error = (...arguments_) => consoleErrors.push(arguments_);

  try {
    await adapter.handleSelection({
      detail: {
        item: { id: 67 },
        showLoading: () => calls.push('loading'),
        showInfo: (content) => calls.push(`info:${content}`),
        showError: (message) => calls.push(`error:${message}`),
      },
      preventDefault: () => calls.push('prevented'),
    });
  } finally {
    globalThis.fetch = originalFetch;
    console.error = originalConsoleError;
  }

  assert.deepEqual(calls, [
    'prevented',
    'loading',
    'error:Translated request error.',
  ]);
  assert.deepEqual(consoleErrors, [[
    'maps2_bayernatlas: info window response error',
    'Missing site settings.',
  ]]);
});

test('uses the translated adapter error for failed requests', async () => {
  const originalFetch = globalThis.fetch;
  const originalConsoleError = console.error;
  const calls = [];
  const adapter = new Maps2InfoWindowAdapter({
    dataset: {
      ajaxUrl: '/maps2-info',
      infoWindow: '1',
      errorMessage: 'Translated request error.',
    },
    addEventListener() {},
  });

  globalThis.fetch = async () => {
    throw new Error('Network unavailable');
  };
  console.error = () => {};

  try {
    await adapter.handleSelection({
      detail: {
        item: { id: 67 },
        showLoading() {},
        showError: (message) => calls.push(message),
      },
      preventDefault() {},
    });
  } finally {
    globalThis.fetch = originalFetch;
    console.error = originalConsoleError;
  }

  assert.deepEqual(calls, ['Translated request error.']);
});

test('leaves generic local content alone when maps2 info windows are disabled', async () => {
  let prevented = false;
  const adapter = new Maps2InfoWindowAdapter({
    dataset: {
      ajaxUrl: '/maps2-info',
      infoWindow: '0',
    },
    addEventListener() {},
  });

  await adapter.handleSelection({
    detail: { item: { id: 67 } },
    preventDefault: () => {
      prevented = true;
    },
  });

  assert.equal(prevented, false);
});

test('maps2 templates keep the explicit five-argument adapter call', () => {
  for (const suffix of ['html', 'fluid.html']) {
    const template = readFileSync(
      new URL(`../../Resources/Private/Templates/PoiCollection/Show.${suffix}`, import.meta.url),
      'utf8',
    );

    assert.doesNotMatch(template, /_all/);
    assert.doesNotMatch(template, /cluster/i);
    assert.match(template, /environment\.settings\.mapRenderer/);
    assert.match(template, /class="maps2"/);

    for (const argument of [
      'contentElementUid',
      'environment',
      'poiCollections',
      'configuration',
      'infoWindow',
    ]) {
      assert.match(template, new RegExp(`${argument}:`));
    }
  }
});

test('documents the intentional TYPO3 13 and 14 renderability difference', () => {
  const templateDirectory = new URL(
    '../../Resources/Private/Templates/PoiCollection/',
    import.meta.url,
  );
  const typo3Version13 = readFileSync(new URL('Show.html', templateDirectory), 'utf8');
  const typo3Version14 = readFileSync(new URL('Show.fluid.html', templateDirectory), 'utf8');

  assert.match(typo3Version13, /maps2 12\.2 has no environment\.isMapRenderable/);
  assert.doesNotMatch(typo3Version13, /condition="\{poiCollections\} &&/);
  assert.match(typo3Version14, /maps2 13\.1 supplies environment\.isMapRenderable/);
  assert.match(typo3Version14, /condition="\{poiCollections\} && \{environment\.isMapRenderable\}"/);
});

test('map partial translates records before calling baf:map', () => {
  const packageDirectory = new URL('../../', import.meta.url);
  const partial = readFileSync(
    new URL('Resources/Private/Partials/BayernAtlas/Map.html', packageDirectory),
    'utf8',
  );

  assert.match(partial, /maps2ba:mapData/);
  assert.match(partial, /<baf:map/);
  assert.match(partial, /items="{mapData\.items}"/);
  assert.match(partial, /configuration="{mapData\.configuration}"/);
  assert.match(partial, /class="maps2-bayernatlas-adapter"/);
  assert.doesNotMatch(partial, /_all/);
});

test('adapter assets contain no generic map implementation', () => {
  const packageDirectory = new URL('../../', import.meta.url);

  for (const suffix of ['html', 'fluid.html']) {
    const partial = readFileSync(
      new URL(`Resources/Private/Partials/BayernAtlas/LoadAssets.${suffix}`, packageDirectory),
      'utf8',
    );

    assert.match(partial, /identifier="maps2-bayernatlas-adapter"/);
    assert.doesNotMatch(partial, /atlas\.bayern\.de\/wc\.js/);
    assert.doesNotMatch(partial, /\.css/);
  }

  assert.equal(
    existsSync(new URL('Resources/Public/JavaScript/BayernAtlas2.js', packageDirectory)),
    false,
  );
  assert.equal(
    existsSync(new URL('Resources/Public/Css/BayernAtlas2.css', packageDirectory)),
    false,
  );
});

test('FlexForm listener supports TYPO3 13 and 14 maps2 identifiers', () => {
  const listener = readFileSync(
    new URL('../../Classes/EventListener/ExtendMaps2FlexForm.php', import.meta.url),
    'utf8',
  );

  assert.match(listener, /'\*,maps2_maps2'/);
  assert.match(listener, /'maps2_maps2'/);
  assert.match(listener, /settings\.mapRenderer/);
  assert.match(listener, /settings\.bayernAtlasShowLayerControl/);
  assert.doesNotMatch(listener, /cluster/i);
});

test('Site Set exposes no unsupported clustering configuration', () => {
  const definitions = readFileSync(
    new URL('../../Configuration/Sets/BayernAtlas/settings.definitions.yaml', import.meta.url),
    'utf8',
  );
  const setup = readFileSync(
    new URL('../../Configuration/Sets/BayernAtlas/setup.typoscript', import.meta.url),
    'utf8',
  );

  assert.doesNotMatch(definitions, /cluster/i);
  assert.doesNotMatch(setup, /cluster/i);
});
