<h2>Hooking the message</h2>

<div show-code>
  <script>
    class Modal {
      constructor(clickEvent) {
        this.clickedElement = clickEvent.target;
      }

      ___message() {
        // get data-message attribute from the clicked element
        // or fallback to 'Hello World'
        return this.clickedElement.dataset.message || 'Hello World';
      }

      ___show() {
        UIkit.modal.alert(this.message());
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
  <button class="modal" data-message="To be or not to be">Show modal</button>
</div>

<p>With this hook you can make the alert close on ESC key or when the background is clicked:</p>

<div show-code>
  <script>
    // see https://getuikit.com/docs/modal#component-options
    ProcessWire.addHookAfter('Modal::message', (event) => {
      let message = event.return;
      event.return = "This message was hooked! <strong>" + message + "</strong>";
    });
  </script>
</div>