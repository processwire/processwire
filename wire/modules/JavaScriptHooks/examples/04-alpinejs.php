<?php $max = 3; ?>

<h2>Alpine.js</h2>

<p>This is a basic Alpine.js counter example without any hooks:</p>

<div show-code>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <div x-data="NoHooksCounter">
    <button @click.prevent="count++">Increment</button>
    <span x-text="count">0</span>
  </div>
  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('NoHooksCounter', (el) => {
        return {
          count: 0,
        };
      });
    })
  </script>
</div>

<p>With just a few little changes we can make it a hookable Alpine.js component!</p>

<div show-code>
  <div x-data="MyCounter">
    <button @click.prevent="decrement()">-</button>
    <span x-text="count">0</span>
    <button @click.prevent="increment()">+</button>
  </div>
  <div x-data="MyCounter">
    <button @click.prevent="decrement()">-</button>
    <span x-text="count">0</span>
    <button @click.prevent="increment()">+</button>
  </div>
  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('MyCounter', (el) => ProcessWire.wire({
          count: 0,

          // alpine will automatically call the init() method whenever
          // it finds a dom element with x-data="MyCounter"
          ___init() {},

          ___increment() {
            this.count++;
          },

          ___decrement() {
            this.count--;
          },
        },
        // for plain objects we need to define the name of the component
        // this is what will be used for the hook selector MyCounter::...
        'MyCounter'
      ));
    })
  </script>
</div>

<p>Notice how the counters respond with extra functionality? Here are the hooks enhancing their behavior:</p>

<div show-code>
  <script>
    // on init we $watch the count property
    // see https://alpinejs.dev/magics/watch
    ProcessWire.addHookAfter('MyCounter::init', (event) => {
      const counter = event.object;
      counter.$watch('count', (value) => {
        UIkit.notification(`Count is now ${value}`, 'success');
        fireConfetti();
      });
    });

    // prevent execution of increment() if count would be greater than max
    ProcessWire.addHookBefore('MyCounter::increment', (event) => {
      if (event.object.count >= <?= $max ?>) {
        UIkit.notification('You havereached the end of the rainbow!', 'danger');
        event.replace = true;
      }
    });

    // prevent execution of decrement() if count would be less than 0
    ProcessWire.addHookBefore('MyCounter::decrement', (event) => {
      if (event.object.count <= 0) {
        UIkit.notification('Count cannot be less than 0', 'danger');
        event.replace = true;
      }
    });
  </script>
</div>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<script>
  const count = 200,
    defaults = {
      origin: {
        y: 0.7
      },
    };

  function fire(particleRatio, opts) {
    confetti(
      Object.assign({}, defaults, opts, {
        particleCount: Math.floor(count * particleRatio),
      })
    );
  }

  function fireConfetti() {
    fire(0.25, {
      spread: 26,
      startVelocity: 55,
    });

    fire(0.2, {
      spread: 60,
    });

    fire(0.35, {
      spread: 100,
      decay: 0.91,
      scalar: 0.8,
    });

    fire(0.1, {
      spread: 120,
      startVelocity: 25,
      decay: 0.92,
      scalar: 1.2,
    });

    fire(0.1, {
      spread: 120,
      startVelocity: 45,
    });
  }
</script>