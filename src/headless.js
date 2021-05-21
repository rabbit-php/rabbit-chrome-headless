function mockPluginsAndMimeTypes() {
    /* global MimeType MimeTypeArray PluginArray */
    // Disguise custom functions as being native
    const makeFnsNative = (fns = []) => {
        const oldCall = Function.prototype.call
        function call() {
            return oldCall.apply(this, arguments)
        }
        // eslint-disable-next-line
        Function.prototype.call = call
        const nativeToStringFunctionString = Error.toString().replace(
            /Error/g,
            'toString'
        )
        const oldToString = Function.prototype.toString
        function functionToString() {
            for (const fn of fns) {
                if (this === fn.ref) {
                    return `function ${fn.name}() { [native code] }`
                }
            }
            if (this === functionToString) {
                return nativeToStringFunctionString
            }
            return oldCall.call(oldToString, this)
        }
        // eslint-disable-next-line
        Function.prototype.toString = functionToString
    }
    const mockedFns = []
    const fakeData = {
        mimeTypes: [
            {
                type: 'application/pdf',
                suffixes: 'pdf',
                description: '',
                __pluginName: 'Chrome PDF Viewer'
            },
            {
                type: 'application/x-google-chrome-pdf',
                suffixes: 'pdf',
                description: 'Portable Document Format',
                __pluginName: 'Chrome PDF Plugin'
            },
            {
                type: 'application/x-nacl',
                suffixes: '',
                description: 'Native Client Executable',
                enabledPlugin: Plugin,
                __pluginName: 'Native Client'
            },
            {
                type: 'application/x-pnacl',
                suffixes: '',
                description: 'Portable Native Client Executable',
                __pluginName: 'Native Client'
            }
        ],
        plugins: [
            {
                name: 'Chrome PDF Plugin',
                filename: 'internal-pdf-viewer',
                description: 'Portable Document Format'
            },
            {
                name: 'Chrome PDF Viewer',
                filename: 'mhjfbmdgcfjbbpaeojofohoefgiehjai',
                description: ''
            },
            {
                name: 'Native Client',
                filename: 'internal-nacl-plugin',
                description: ''
            }
        ],
        fns: {
            namedItem: instanceName => {
                // Returns the Plugin/MimeType with the specified name.
                const fn = function (name) {
                    if (!arguments.length) {
                        throw new TypeError(
                            `Failed to execute 'namedItem' on '${instanceName}': 1 argument required, but only 0 present.`
                        )
                    }
                    return this[name] || null
                }
                mockedFns.push({ ref: fn, name: 'namedItem' })
                return fn
            },
            item: instanceName => {
                // Returns the Plugin/MimeType at the specified index into the array.
                const fn = function (index) {
                    if (!arguments.length) {
                        throw new TypeError(
                            `Failed to execute 'namedItem' on '${instanceName}': 1 argument required, but only 0 present.`
                        )
                    }
                    return this[index] || null
                }
                mockedFns.push({ ref: fn, name: 'item' })
                return fn
            },
            refresh: instanceName => {
                // Refreshes all plugins on the current page, optionally reloading documents.
                const fn = function () {
                    return undefined
                }
                mockedFns.push({ ref: fn, name: 'refresh' })
                return fn
            }
        }
    }
    // Poor mans _.pluck
    const getSubset = (keys, obj) =>
        keys.reduce((a, c) => ({ ...a, [c]: obj[c] }), {})
    function generateMimeTypeArray() {
        const arr = fakeData.mimeTypes
            .map(obj => getSubset(['type', 'suffixes', 'description'], obj))
            .map(obj => Object.setPrototypeOf(obj, MimeType.prototype))
        arr.forEach(obj => {
            arr[obj.type] = obj
        })
        // Mock functions
        arr.namedItem = fakeData.fns.namedItem('MimeTypeArray')
        arr.item = fakeData.fns.item('MimeTypeArray')
        return Object.setPrototypeOf(arr, MimeTypeArray.prototype)
    }
    const mimeTypeArray = generateMimeTypeArray()
    Object.defineProperty(navigator, 'mimeTypes', {
        get: () => mimeTypeArray
    })
    function generatePluginArray() {
        const arr = fakeData.plugins
            .map(obj => getSubset(['name', 'filename', 'description'], obj))
            .map(obj => {
                const mimes = fakeData.mimeTypes.filter(
                    m => m.__pluginName === obj.name
                )
                // Add mimetypes
                mimes.forEach((mime, index) => {
                    navigator.mimeTypes[mime.type].enabledPlugin = obj
                    obj[mime.type] = navigator.mimeTypes[mime.type]
                    obj[index] = navigator.mimeTypes[mime.type]
                })
                obj.length = mimes.length
                return obj
            })
            .map(obj => {
                // Mock functions
                obj.namedItem = fakeData.fns.namedItem('Plugin')
                obj.item = fakeData.fns.item('Plugin')
                return obj
            })
            .map(obj => Object.setPrototypeOf(obj, Plugin.prototype))
        arr.forEach(obj => {
            arr[obj.name] = obj
        })
        // Mock functions
        arr.namedItem = fakeData.fns.namedItem('PluginArray')
        arr.item = fakeData.fns.item('PluginArray')
        arr.refresh = fakeData.fns.refresh('PluginArray')
        return Object.setPrototypeOf(arr, PluginArray.prototype)
    }
    const pluginArray = generatePluginArray()
    Object.defineProperty(navigator, 'plugins', {
        get: () => pluginArray
    })
    // Make mockedFns toString() representation resemble a native function
    makeFnsNative(mockedFns)
}
try {
    const isPluginArray = navigator.plugins instanceof PluginArray
    const hasPlugins = isPluginArray && navigator.plugins.length > 0
    if (!(isPluginArray && hasPlugins)) {
        mockPluginsAndMimeTypes()
    }

    Object.defineProperties(navigator, {
        'appVersion': { get: () => '5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.72 Safari/537.36' },
        'userAgent': { get: () => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.72 Safari/537.36' },
        'languages': { get: () => ['zh-CN', 'zh', 'en'] },
        'deviceMemory': { get: () => 8 },
        'hardwareConcurrency': { get: () => 8 },
        'platform': { get: () => 'Win32' }
    });
    Object.defineProperty(navigator.connection, 'rtt', { get: () => 50 });
    window.navigator.chrome = { runtime: {} };
    // webdriver
    const newProto = navigator.__proto__;
    delete newProto.webdriver; //删除 navigator.webdriver字段
    navigator.__proto__ = newProto;

    // chrome
    window.chrome = {};
    window.chrome.app = {
        InstallState: 'hehe',
        RunningState: 'haha',
        getDetails: 'xixi',
        getIsInstalled: 'ohno',
    };
    window.chrome.csi = function () { };
    window.chrome.loadTimes = function () { };
    window.chrome.runtime = function () { };

    // permissions
    const originalQuery = window.navigator.permissions.query; //notification伪装
    window.navigator.permissions.query = (parameters) => parameters.name === 'notifications' ? Promise.resolve({ state: Notification.permission }) : originalQuery(parameters);

    //inc
    const getParameter = WebGLRenderingContext.getParameter;
    WebGLRenderingContext.prototype.getParameter = function (parameter) {
        // UNMASKED_VENDOR_WEBGL
        if (parameter === 37445) {
            return 'Google Inc. (Intel)';
        }
        // UNMASKED_RENDERER_WEBGL
        if (parameter === 37446) {
            return 'ANGLE (Intel, Intel(R) UHD Graphics 620 Direct3D11 vs_5_0 ps_5_0, D3D11-27.20.100.8681)';
        }
        return getParameter(parameter);
    };
} catch (err) { }