/**
 * PWImageResizer: Client-side resizing of images (JPG, PNG, GIF)
 *
 * Code based on ImageUploader (c) Ross Turner (https://github.com/rossturner/HTML5-ImageUploader).
 * Adapted for ProcessWire by Ryan as a resizer-only libary with different behavior and some fixes. 
 *
 * Requires exif.js (https://github.com/exif-js/exif-js) for JPEG autoRotate functions. 
 * 
 * Config settings:
 * 
 * - `maxWidth` (int): An integer in pixels for the maximum width allowed for uploaded images, selected images 
 *    with a greater width than this value will be scaled down before upload. (default=0)
 *    Note: if no maxWidth is specified and maxHeight is, then maxHeight is also used for maxWidth.
 *    If neither maxWidth or maxHeight are specified, then 1024 is used for both. 
 *      
 * - `maxHeight` (int): An integer in pixels for the maximum height allowed for uploaded images, selected images 
 *    with a greater height than this value will be scaled down before upload. (default=0)
 *    Note: if no maxHeight is specified and maxWidth is, then maxWidth is also used for maxHeight.
 *    If neither maxWidth or maxHeight are specified, then 1024 is used for both. 
 *      
 * - `maxSize` (float): A float value in megapixels (MP) for the maximum overall size of the image allowed for 
 *    uploaded images, selected images with a greater size than this value will be scaled down before upload. 
 *    The size of the image is calculated by the formula size = width * height / 1000000, where width and height 
 *    are the dimensions of the image in pixels. If the value is null or is not specified, then maximum size 
 *    restriction is not applied. Default value: null. For websites it's good to set this value around 1.7: 
 *    for landscape images taken by standard photo cameras (Canon, Nikon, etc.), this value will lead to 
 *    scaling down the original photo to size about 1600 x 1000 px, which is sufficient for displaying the 
 *    scaled image on large screen monitors. 
 *      
 * - `scaleRatio` (float): Allows scaling down to a specified fraction of the original size. 
 *    (Example: a value of 0.5 will reduce the size by half.) Accepts a decimal value between 0 and 1.
 * 
 * - `quality` (float): A float between 0.1 and 1.0 for the image quality to use in the resulting image data, 
 *    around 0.9 is recommended. Default value: 1.0. Applies to JPEG images only.
 *    
 * - `autoRotate` (bool): Correct image orientation when EXIF data suggests it should be? (default=true).
 *    Note: autoRotate is not applied if it is determined that image needs no resize.
 *    
 * - `debug` (bool): Output verbose debugging messages to javascript console.
 *      
 *    
 * Example usage:   
 *
 *    // note: “file” variable is File object from “input[type=file].files” array
 *    var resizer = new PWImageResizer({
 *      maxWidth: 1600, 
 *      maxHeight: 1200,
 *      quality: 0.9
 *    });
 *    resizer.resize(file, function(imageData) {
 *      if(imageData == false) {
 *        // no resize necessary, you can just upload file as-is
 *      } else {
 *        // upload the given resized imageData rather than file
 *      }
 *    });
 *    
 *    
 * LICENSE (from original ImageUploader files by Ross Turner):    
 * 
 * Copyright (c) 2012 Ross Turner and contributors (https://github.com/zsinj)
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */
var PWImageResizer = function(config) {
	this.setConfig(config);
};

/**
 * Primary public API to PWImageResizer
 * 
 * @param file File to resize (single “File” object item from an “input[type=file].files” array)
 * @param completionCallback Callback function upon completion, receives single ImageData argument.
 *   Receives populated ImageData when resize was necessary and completed. 
 *   Receives boolean false for ImageData when no resize is necessary.
 * 
 */
PWImageResizer.prototype.resize = function(file, completionCallback) {
	var img = document.createElement('img');

	this.currentFile = file;
	
	var reader = new FileReader();
	var This = this;
	var contentType = file.type.toString();
	
	reader.onload = function(e) {
		img.src = e.target.result;
		
		img.onload = function() {
			if(!This.needsResize(img, contentType)) {
				// early exit when no resize necessary 
				// return false to callback, indicating that no resize is needed
				completionCallback(false);
				return;
			}

			if(contentType == 'image/jpeg' && This.config.autoRotate) {
				// jpeg with autoRotate 
				This.consoleLog('detecting JPEG image orientation...');
				
				if((typeof EXIF.getData === "function") && (typeof EXIF.getTag === "function")) {
					This.consoleLog('EXIF.getData starting');
					EXIF.getData(img, function() {
						This.consoleLog('EXIF.getData done, orientation:');
						var orientation = EXIF.getTag(this, "Orientation");
						This.consoleLog('image orientation from EXIF tag: ' + orientation);
						This.scaleImage(img, orientation, completionCallback);
					});
				} else {
					This.consoleLog("can't read EXIF data, the Exif.js library not found");
					This.scaleImage(img, 0, completionCallback);
				}
				
			} else {
				// png or gif (or jpeg with autoRotate==false)
				This.scaleImage(img, 0, completionCallback);
			}
		}
	};
	
	reader.readAsDataURL(file);
};

/**
 * Return whether or not image needs client-side resize performed 
 * 
 * This function not part of the original ImageUploader library.
 * 
 * @param img The <img> element
 * @param contentType Content-type of the image, i.e. "image/jpeg", "image/png", "image/gif"
 * @returns {boolean}
 * 
 */
PWImageResizer.prototype.needsResize = function(img, contentType) {
	var needsResize = false;
	var why = 'n/a';

	if(contentType != 'image/jpeg' && contentType != 'image/png' && contentType != 'image/gif') {
		// content-type is not a supported image format
		why = 'unsupported image content-type: ' + contentType;

	} else if(this.config.scaleRatio > 0) {
		// always proceed when scaleRatio is used
		needsResize = true;
		why = 'scaleRatio specified';

	} else if(this.config.maxWidth > 0 || this.config.maxHeight > 0) {
		// check dimensions
		if(this.config.maxWidth > 0 && img.width > this.config.maxWidth) needsResize = true;
		if(this.config.maxHeight > 0 && img.height > this.config.maxHeight) needsResize = true;
		why = needsResize ? 'dimensions exceed max allowed' : 'dimensions do not require resize';
	}

	if(!needsResize && this.config.maxSize > 0) {
		// check max allowed megapixels
		if(this.config.maxSize < (img.width * img.height) / 1000000) needsResize = true;
		why = (needsResize ? 'megapixels exceeds ' : 'megapixels below ') + this.config.maxSize;
	}

	if(this.config.debug) {
		this.consoleLog('needsResize=' + (needsResize ? 'Yes' : 'No') + ' (' + why + ')');
	}
	
	return needsResize;
};

PWImageResizer.prototype.drawImage = function(context, img, x, y, width, height, deg, flip, flop, center) {
	context.save();

	if(typeof width === "undefined") width = img.width;
	if(typeof height === "undefined") height = img.height;
	if(typeof center === "undefined") center = false;

	// Set rotation point to center of image, instead of top/left
	if(center) {
		x -= width/2;
		y -= height/2;
	}

	// Set the origin to the center of the image
	context.translate(x + width/2, y + height/2);

	// Rotate the canvas around the origin
	var rad = 2 * Math.PI - deg * Math.PI / 180;
	context.rotate(rad);

	// Flip/flop the canvas
	if(flip) flipScale = -1; else flipScale = 1;
	if(flop) flopScale = -1; else flopScale = 1;
	context.scale(flipScale, flopScale);

	// Draw the image    
	context.drawImage(img, -width/2, -height/2, width, height);

	context.restore();
}

/**
 * Scale an image
 * 
 * @param img The <img> element
 * @param orientation Orientation number from Exif.js or 0 bypass
 * @param completionCallback Function to call upon completion
 * 
 */
PWImageResizer.prototype.scaleImage = function(img, orientation, completionCallback) {
	var canvas = document.createElement('canvas');
	
	canvas.width = img.width;
	canvas.height = img.height;
	
	var ctx = canvas.getContext('2d');
	ctx.save();

	// Good explanation of EXIF orientation is here: 
	// http://www.daveperrett.com/articles/2012/07/28/exif-orientation-handling-is-a-ghetto/
	
	var width = canvas.width;
	var styleWidth = canvas.style.width;
	var height = canvas.height;
	var styleHeight = canvas.style.height;
	
	if(typeof orientation === 'undefined') orientation = 1;
	
	if(orientation) {
		if(orientation > 4) {
			canvas.width = height;
			canvas.style.width = styleHeight;
			canvas.height = width;
			canvas.style.height = styleWidth;
		}
		switch(orientation) {
			case 2:
				ctx.translate(width, 0);
				ctx.scale(-1, 1);
				break;
			case 3:
				ctx.translate(width, height);
				ctx.rotate(Math.PI);
				break;
			case 4:
				ctx.translate(0, height);
				ctx.scale(1, -1);
				break;
			case 5:
				ctx.rotate(0.5 * Math.PI);
				ctx.scale(1, -1);
				break;
			case 6:
				ctx.rotate(0.5 * Math.PI);
				ctx.translate(0, -height);
				break;
			case 7:
				ctx.rotate(0.5 * Math.PI);
				ctx.translate(width, -height);
				ctx.scale(-1, 1);
				break;
			case 8:
				ctx.rotate(-0.5 * Math.PI);
				ctx.translate(-width, 0);
				break;
		}
	}
	ctx.drawImage(img, 0, 0);
	ctx.restore();

	//Lets find the max available width for scaled image
	var ratio = canvas.width / canvas.height;
	var mWidth = 0; 
	var resizeType = '';
	
	if(this.config.maxWidth > 0 || this.config.maxHeight > 0) {
		mWidth = Math.min(this.config.maxWidth, ratio * this.config.maxHeight);
		resizeType = 'max width/height of ' + this.config.maxWidth + 'x' + this.config.maxHeight;
	}

	if(this.config.maxSize > 0 && (this.config.maxSize < (canvas.width * canvas.height) / 1000000)) { 
		var mSize = Math.floor(Math.sqrt(this.config.maxSize * ratio) * 1000);
		mWidth = mWidth > 0 ? Math.min(mWidth, mSize) : mSize;
		if(mSize === mWidth) resizeType = 'max megapixels of ' + this.config.maxSize;
	}

	if(this.config.scaleRatio) {
		var mScale = Math.floor(this.config.scaleRatio * canvas.width);
		mWidth = mWidth > 0 ? Math.min(mWidth, mScale) : mScale;
		if(mScale == mWidth) resizeType = 'scale ratio of ' + this.config.scaleRatio; 
	}

	if(mWidth <= 0) {
		// mWidth = 1;
		this.consoleLog('image size is too small to resize');
		completionCallback(false);
		return;
	}
	
	if(this.config.debug) {
		this.consoleLog('original image size: ' + canvas.width + 'x' + canvas.height + ' px');
		this.consoleLog('scaled image size: ' + mWidth + 'x' + Math.floor(mWidth / ratio) + ' px via ' + resizeType);
	}

	while(canvas.width >= (2 * mWidth)) {
		canvas = this.getHalfScaleCanvas(canvas);
	}

	if(canvas.width > mWidth) {
		canvas = this.scaleCanvasWithAlgorithm(canvas, mWidth);
	}

	var quality = this.config.quality;
	if(this.currentFile.type != 'image/jpeg') quality = 1.0;
	var imageData = canvas.toDataURL(this.currentFile.type, quality);

	if(typeof this.config.onScale === 'function') {
		this.config.onScale(imageData);
	}

	completionCallback(this.imageDataToBlob(imageData));
};

/**
 * Convert base64 canvas image data to a BLOB
 * 
 * This base64 decodes data so that it can be sent to the server as regular file data, rather than
 * data that needs base64 decoding at the server side. 
 *
 * Source: http://stackoverflow.com/questions/23945494/use-html5-to-resize-an-image-before-upload
 * (This function is not part of the original ImageUploader library)
 * 
 */
PWImageResizer.prototype.imageDataToBlob = function(imageData) {
	var base64Marker = ';base64,';

	if(imageData.indexOf(base64Marker) == -1) {
		var parts = imageData.split(',');
		var contentType = parts[0].split(':')[1];
		var raw = parts[1];
		return new Blob([raw], { type: contentType });
	}

	var parts = imageData.split(base64Marker);
	var contentType = parts[0].split(':')[1];
	var raw = window.atob(parts[1]);
	var rawLength = raw.length;

	var uInt8Array = new Uint8Array(rawLength);

	for (var i = 0; i < rawLength; ++i) {
		uInt8Array[i] = raw.charCodeAt(i);
	}

	return new Blob([uInt8Array], { type: contentType });
};

PWImageResizer.prototype.scaleCanvasWithAlgorithm = function(canvas, maxWidth) {
	var scaledCanvas = document.createElement('canvas');
	var scale = maxWidth / canvas.width;

	scaledCanvas.width = canvas.width * scale;
	scaledCanvas.height = canvas.height * scale;

	var srcImgData = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
	var destImgData = scaledCanvas.getContext('2d').createImageData(scaledCanvas.width, scaledCanvas.height);

	this.applyBilinearInterpolation(srcImgData, destImgData, scale);

	scaledCanvas.getContext('2d').putImageData(destImgData, 0, 0);

	return scaledCanvas;
};

PWImageResizer.prototype.getHalfScaleCanvas = function(canvas) {
	var halfCanvas = document.createElement('canvas');

	halfCanvas.width = canvas.width / 2;
	halfCanvas.height = canvas.height / 2;

	halfCanvas.getContext('2d').drawImage(canvas, 0, 0, halfCanvas.width, halfCanvas.height);

	return halfCanvas;
};

PWImageResizer.prototype.applyBilinearInterpolation = function(srcCanvasData, destCanvasData, scale) {
	function inner(f00, f10, f01, f11, x, y) {
		var un_x = 1.0 - x;
		var un_y = 1.0 - y;
		return (f00 * un_x * un_y + f10 * x * un_y + f01 * un_x * y + f11 * x * y);
	}
	var i, j;
	var iyv, iy0, iy1, ixv, ix0, ix1;
	var idxD, idxS00, idxS10, idxS01, idxS11;
	var dx, dy;
	var r, g, b, a;
	for (i = 0; i < destCanvasData.height; ++i) {
		iyv = i / scale;
		iy0 = Math.floor(iyv);
		// Math.ceil can go over bounds
		iy1 = (Math.ceil(iyv) > (srcCanvasData.height - 1) ? (srcCanvasData.height - 1) : Math.ceil(iyv));
		for (j = 0; j < destCanvasData.width; ++j) {
			ixv = j / scale;
			ix0 = Math.floor(ixv);
			// Math.ceil can go over bounds
			ix1 = (Math.ceil(ixv) > (srcCanvasData.width - 1) ? (srcCanvasData.width - 1) : Math.ceil(ixv));
			idxD = (j + destCanvasData.width * i) * 4;
			// matrix to vector indices
			idxS00 = (ix0 + srcCanvasData.width * iy0) * 4;
			idxS10 = (ix1 + srcCanvasData.width * iy0) * 4;
			idxS01 = (ix0 + srcCanvasData.width * iy1) * 4;
			idxS11 = (ix1 + srcCanvasData.width * iy1) * 4;
			// overall coordinates to unit square
			dx = ixv - ix0;
			dy = iyv - iy0;
			// I let the r, g, b, a on purpose for debugging
			r = inner(srcCanvasData.data[idxS00], srcCanvasData.data[idxS10], srcCanvasData.data[idxS01], srcCanvasData.data[idxS11], dx, dy);
			destCanvasData.data[idxD] = r;

			g = inner(srcCanvasData.data[idxS00 + 1], srcCanvasData.data[idxS10 + 1], srcCanvasData.data[idxS01 + 1], srcCanvasData.data[idxS11 + 1], dx, dy);
			destCanvasData.data[idxD + 1] = g;

			b = inner(srcCanvasData.data[idxS00 + 2], srcCanvasData.data[idxS10 + 2], srcCanvasData.data[idxS01 + 2], srcCanvasData.data[idxS11 + 2], dx, dy);
			destCanvasData.data[idxD + 2] = b;

			a = inner(srcCanvasData.data[idxS00 + 3], srcCanvasData.data[idxS10 + 3], srcCanvasData.data[idxS01 + 3], srcCanvasData.data[idxS11 + 3], dx, dy);
			destCanvasData.data[idxD + 3] = a;
		}
	}
};

PWImageResizer.prototype.setConfig = function(customConfig) {
	this.config = customConfig;
	this.config.debug = this.config.debug || false;

	if(typeof customConfig.quality == "undefined") customConfig.quality = 1.0;
	if(customConfig.quality < 0.1) customConfig.quality = 0.1; 
	if(customConfig.quality > 1.0) customConfig.quality = 1.0;
	this.config.quality = customConfig.quality;
	
	if((!this.config.maxWidth) || (this.config.maxWidth < 0)) {
		this.config.maxWidth = 0;
	}
	if((!this.config.maxHeight) || (this.config.maxHeight < 0)) {
		this.config.maxHeight = 0;
	}
	if((!this.config.maxSize) || (this.config.maxSize < 0)) {
		this.config.maxSize = null;
	}
	if((!this.config.scaleRatio) || (this.config.scaleRatio <= 0) || (this.config.scaleRatio >= 1)) {
		this.config.scaleRatio = null;
	}
	this.config.autoRotate = true;
	if(typeof customConfig.autoRotate === 'boolean')
		this.config.autoRotate = customConfig.autoRotate;

	// ensure both dimensions are provided (ryan)
	if(this.config.maxWidth && !this.config.maxHeight) {
		this.config.maxHeight = this.config.maxWidth;
	} else if(this.config.maxHeight && !this.config.maxWidth) {
		this.config.maxWidth = this.config.maxHeight;
	} else if(!this.config.maxWidth && !this.config.maxHeight) {
		// use default settings (0=disabled)
	}
};

PWImageResizer.prototype.consoleLog = function(msg) {
	if(this.config.debug) console.log('PWImageResizer: ' + msg);
};


