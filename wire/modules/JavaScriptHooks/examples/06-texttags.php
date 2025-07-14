<h2>TextTags</h2>

<div show-code>
  <script>
    // self-invoking function to create a new class TextTags
    // but do not add it to the global namespace
    (() => {
      class TextTags {

        // NOTE: this does NOT work
        // you can't call a hookable method from the constructor
        // because at that point the class is not yet hookable
        // constructor(text) {
        //   this.log(text);
        // }

        ___log(text) {
          alert(text);
        }
      }

      // add TextTags() as global function
      window.TextTags = function(text) {
        const instance = ProcessWire.wire(new TextTags());
        return instance.log(text);
      };
    })();

    // add demo hook to modify the log() behavior
    ProcessWire.addHookBefore("TextTags::log", (event) => {
      const msg = event.arguments(0);
      if (msg === "Hello, world!") event.arguments(0, "Hello, universe!");
    });

    TextTags("Hello, world!");
  </script>
</div>