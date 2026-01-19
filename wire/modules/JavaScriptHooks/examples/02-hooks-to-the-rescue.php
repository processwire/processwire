<h2>Hooks to the rescue</h2>

<p>Now let's make some adjustments to the code to make the modal hookable!</p>

<div show-code>
  <script>
    class Modal {
      constructor(clickEvent) {
        this.clickedElement = clickEvent.target;
      }

      // NEW: add three underscores to the method name
      ___show() {
        UIkit.modal.alert('Hello World');
      }
    }

    // no changes here
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.modal')) return;
      e.preventDefault();
      const modal = ProcessWire.wire(new Modal(e));
      modal.show();
    });
  </script>
  <button class="modal">Show modal</button>
</div>

<p>Now that the <code>show()</code> method is hookable, we can add a hook to it. Something like this:</p>

<div show-code>
  <script>
    ProcessWire.addHookBefore('Modal::show', (event) => {
      // get the element that was clicked
      const modal = event.object;
      const clickedElement = modal.clickedElement;

      // if it has no .confirm class, return
      if (!clickedElement.classList.contains('confirm')) return;

      // otherwise replace the original event and show confirmation popup
      event.replace = true;
      UIkit.modal.confirm('Click CANCEL and see what happens?').then(
        // success
        () => {
          console.log('confirmed');
          // temporarily remove the .confirm class and click the element again
          clickedElement.classList.remove('confirm');
          clickedElement.click();
          clickedElement.classList.add('confirm');
        },
        // cancel
        () => {
          console.log('cancelled');
        },
      );
    });
  </script>
  <button class="modal confirm">Show modal after confirmation</button>
</div>

<p>As you can see, the confirmation popup is shown, and if you click CANCEL, the original modal is not shown.</p>

<p>This is a simple example, but you can use JavaScriptHooks to do much more.</p>