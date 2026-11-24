class HooksHelper {
  hooks = {
    after: {},
    before: {},
  };

  addHookAfter(name, fn, priority = 100) {
    const _hooks = this.hooks.after[name] || [];
    _hooks.push({ name, fn, priority });
    _hooks.sort((a, b) => a.priority - b.priority);
    this.hooks.after[name] = _hooks;
  }

  addHookBefore(name, fn, priority = 100) {
    const _hooks = this.hooks.before[name] || [];
    _hooks.push({ name, fn, priority });
    _hooks.sort((a, b) => a.priority - b.priority);
    this.hooks.before[name] = _hooks;
  }

  /**
   * Execute all attached before and after hooks
   */
  executeHooks(type, hookName, hookEvent) {
    const _hooks = this.hooks[type][hookName] || [];
    for (let i = 0; i < _hooks.length; i++) {
      try {
        // get the hook and execute its "fn" callback
        // send the hookEvent to this callback to support our familiar syntax:
        // addHookAfter('...', function(event) { ... });
        _hooks[i].fn(hookEvent);

        // if the callback has set replace to true we stop here
        // as far as I know only before hooks can replace following hooks
        // so we do this only for before hooks
        if (hookEvent.replace && type === "before") break;
      } catch (error) {
        console.error(`Error in ${type} hook for ${hookName}:`, error);
        console.log("Hook:", _hooks[i]);
        console.log("HookEvent:", hookEvent);
      }
    }
  }

  /**
   * Return the hook handler that delegates method calls to the corresponding
   * hookable method, if it exists. For example calling .foo() will delegate
   * to ___foo()
   */
  hookHandler() {
    const self = this; // Store reference to HooksHelper instance
    return {
      get: function (data, prop) {
        const object = data.object;
        if (typeof prop !== "string") return object[prop];

        // build hook selector
        let hookObjectName = data.name;
        if (!hookObjectName) hookObjectName = object.constructor.name;
        const selector = `${hookObjectName}::${prop}`;
        // console.log(selector);

        // if prop starts with ___ we return the original value
        if (prop.startsWith("___")) return object[prop];

        // if ___prop is not defined we return the original value
        if (typeof object[`___${prop}`] === "undefined") return object[prop];

        // if prop does not start with ___ we return a function that executes
        // hooks and the original method
        return function (...args) {
          // Create hookEvent object using HookEvent class
          const hookEvent = new HookEvent({
            arguments: args,
            object: this,
          });

          // Execute before hooks using the HooksHelper instance
          self.executeHooks("before", selector, hookEvent);

          // if event.replace is true we do not call the original method
          if (hookEvent.replace) return hookEvent.return;

          // Call the original method
          hookEvent.return = object[`___${prop}`].apply(
            this,
            hookEvent.arguments()
          );

          // Execute after hooks using the HooksHelper instance
          self.executeHooks("after", selector, hookEvent);

          return hookEvent.return;
        };
      },
    };
  }

  /**
   * Wire an object (make it hookable)
   */
  wire(object, name = null, noProxy = false) {
    // check if object is an instance of a class
    // if it is, throw an error
    if (object.constructor.name !== "Object") {
      name = object.constructor.name;
    }

    // if the object is not a class it will have name "Object"
    // in that case we throw an error so that the developer provides a name
    // that we can use for the hook identifier like "Foo::hello" or otherwise
    // all generic objects would have the same hook name "Object::hello"
    if (!name) {
      throw new Error("Please provide a name: ProcessWire.wire(object, name)");
    }

    // for generic objects we always use the non-proxy version
    if (object.constructor.name === "Object") noProxy = true;

    // for classes we use the proxy
    // for everything else we use the non-proxy version (alpine js, plain objects)
    if (noProxy) return this.wireNoProxy(object, name);
    else return this.wireProxy(object, name);
  }

  /**
   * Make an object hookable without using proxies
   * This is for situations where proxies might interfere with other libraries
   * for example when having an alpine component that is itself a proxy.
   * When using this method we simply look for methods with ___ prefix and
   * add the corresponding non-prefixed methods to the object that will take
   * care of executing before and after hooks when called.
   */
  wireNoProxy(object, name) {
    const self = this; // Store reference to HooksHelper instance
    // loop all properties of the object
    // and add corresponding methods instead of methods with ___ prefix
    let props = Object.getOwnPropertyDescriptors(object);
    for (let key in props) {
      // non prefixed props stay untouched
      if (!key.startsWith("___")) continue;

      // get the original method
      // we only support hookable methods at this point, no properties
      const originalMethod = props[key].value;
      if (typeof originalMethod !== "function") continue;

      // generate new method name, eg MyClass::myMethod
      let newMethod = key.slice(3);
      const hookName = `${name}::${newMethod}`;

      // add the new method to the object
      props[newMethod] = {
        value: function (...args) {
          // Create hookEvent object using HookEvent class
          const hookEvent = new HookEvent({
            arguments: args,
            object: this,
          });

          // Execute before hooks using the HooksHelper instance
          self.executeHooks("before", hookName, hookEvent);

          // if event.replace is true we do not call the original method
          if (hookEvent.replace) return hookEvent.return;

          // Call the original method
          hookEvent.return = originalMethod.apply(this, hookEvent.arguments());

          // Execute after hooks using the HooksHelper instance
          self.executeHooks("after", hookName, hookEvent);

          return hookEvent.return;
        },
      };
    }

    // create the new object and return it
    return Object.create(Object.getPrototypeOf(object), props);
  }

  /**
   * Wire an object using proxies
   * This is the default method and should be used in most cases.
   */
  wireProxy(object, name) {
    return new Proxy(
      {
        object: object,
        name: name,
      },
      this.hookHandler() // Fix: Use this.hookHandler() instead of HookHandler
    );
  }
}

// trigger HooksHelper:ready event and pass the instance to it
document.dispatchEvent(
  new CustomEvent("HooksHelper:ready", { detail: new HooksHelper() })
);
