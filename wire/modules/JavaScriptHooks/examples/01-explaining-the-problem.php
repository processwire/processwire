<h2>Explaining the problem</h2>

<p>Imagine we have a simple modal class like this:</p>

<div show-code>
  <script>
    class Modal {
      show() {
        UIkit.modal.alert('Hello World');
      }
    }

    // create an instance of the class
    const modal = new Modal();

    // listen for clicks on elements with the class "modal"
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.modal')) return;
      e.preventDefault();
      modal.show();
    });
  </script>
  <button class="modal">Show modal</button>
</div>

<p>Now let's assume we want to show the modal only after a confirmation has been given. You might think we can do this with another event listener. Something like this:</p>

<div show-code>
  <script>
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.modal')) return;
      if (!e.target.closest('.confirm')) return;
      e.preventDefault();
      UIkit.modal.confirm('Click CANCEL and see what happens?').then(
        // success
        () => {
          console.log('confirmed');
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

<p>As you can see, the confirmation popup is shown, but no matter what you do, the original modal is shown as well! This is because we can not modify the modal's <code>show()</code> method.</p>

<p>JavaScriptHooks to the rescue!</p>