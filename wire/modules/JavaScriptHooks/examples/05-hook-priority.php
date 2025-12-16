<h2>Hook Priority</h2>

<div show-code>
  <script>
    class HelloWorld {
      ___hello() {
        alert('hello');
      }
    }

    // make helloworld hookable
    const helloWorld = ProcessWire.wire(new HelloWorld());

    // listen for clicks on the button
    document.addEventListener('click', (e) => {
      const el = e.target.closest('.hello');
      if (!el) return;
      e.preventDefault();
      helloWorld.hello();
    });
  </script>
  <button class='hello'>Click me</button>
</div>

<p>Now let's add several hooks to the same method with different priorities:</p>

<div show-code>
  <script>
    ProcessWire.addHookAfter('HelloWorld::hello', (event) => {
      alert('1');
    }, 300);
    ProcessWire.addHookAfter('HelloWorld::hello', (event) => {
      alert('2');
    }, 100);
    ProcessWire.addHookAfter('HelloWorld::hello', (event) => {
      alert('3');
    }, 200);
  </script>
</div>

<p>Note that hooks have been added in order <strong>1-2-3</strong>, but they execute in order <strong>2-3-1</strong>!</p>