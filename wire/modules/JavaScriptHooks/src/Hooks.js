// create global ProcessWire object if it doesn't exist
// This makes it possible to use ProcessWire hooks in the frontend (standalone)
if (typeof ProcessWire == "undefined") ProcessWire = {};

// add methods to the ProcessWire object as soon as the HooksHelper is ready
document.addEventListener("HooksHelper:ready", (e) => {
  const HooksHelper = e.detail;
  // Bind the methods to the HooksHelper instance to preserve 'this' context
  ProcessWire.wire = HooksHelper.wire.bind(HooksHelper);
  ProcessWire.addHookAfter = HooksHelper.addHookAfter.bind(HooksHelper);
  ProcessWire.addHookBefore = HooksHelper.addHookBefore.bind(HooksHelper);
});
