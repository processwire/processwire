import {HooksHelper} from "./includes/HooksHelper";

// create global ProcessWire object if it doesn't exist
// This makes it possible to use ProcessWire hooks in the frontend (standalone)
if (typeof ProcessWire == "undefined") ProcessWire = {};

const helper = new HooksHelper();
// Bind the methods to the HooksHelper instance to preserve 'this' context
ProcessWire.wire = helper.wire.bind(helper);
ProcessWire.addHookAfter = helper.addHookAfter.bind(helper);
ProcessWire.addHookBefore = helper.addHookBefore.bind(helper);
