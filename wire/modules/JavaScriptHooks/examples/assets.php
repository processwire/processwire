<link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/themes/prism-tomorrow.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/prism.min.js"></script>
<script>
  document.querySelectorAll('[show-code]').forEach(el => {
    code = el.innerHTML;
    // remove the first line
    code = code.split('\n').slice(1).join('\n');
    // remove the first two spaces from each line
    code = code.split('\n').map(line => line.slice(2)).join('\n');

    // highlight the code
    code = Prism.highlight(code, Prism.languages.html);
    // console.log(code);

    // create a new <pre> tag right after the element
    const pre = document.createElement('pre');
    pre.classList.add('language-html');
    pre.innerHTML = code;
    el.before(pre);
  });
</script>