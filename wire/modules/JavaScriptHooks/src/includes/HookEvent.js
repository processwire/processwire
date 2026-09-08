/**
 * HookEvent class to use in hooks
 *
 * This class provides the familiar syntax that we know from PHP hooks:
 * - event.object
 * - event.arguments
 * - event.replace
 * - event.return
 */
class HookEvent {
  constructor(data) {
    this.object = data.object;
    this.args = data.arguments;
    this.replace = false;
    this.return = data.return;
  }

  /**
   * Dynamic arguments getter
   *
   * This is to support the familiar syntax:
   *
   * Get the arguments array:
   * event.arguments
   *
   * Get a single argument:
   * event.arguments(0)
   *
   * Set a single argument:
   * event.arguments(0, "foo")
   */
  get arguments() {
    const self = this;
    return new Proxy(
      function () {
        // requested as property
        // event.arguments -> returns the arguments array
        if (arguments.length === 0) return self.args;

        // requested as method
        // event.arguments(0) -> returns the requested array element
        if (arguments.length === 1) return self.args[arguments[0]];

        // requested as method to set a value
        // event.arguments(0, "foo") -> sets the requested array element
        if (arguments.length === 2) self.args[arguments[0]] = arguments[1];
      },
      {
        get(target, prop) {
          if (prop === "length") return self.args.length;
          const index = parseInt(prop, 10);
          return isNaN(index) ? undefined : self.args[index];
        },
        set(target, prop, value) {
          const index = parseInt(prop, 10);
          if (!isNaN(index)) {
            self.args[index] = value;
            return true;
          }
          return false;
        },
      }
    );
  }
}
