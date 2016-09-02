/**
 * mystyles.js
 *
 * This file may be used when you have "Styles" as one of the items in your toolbar.
 *
 * For a more comprehensive example, see the file ./ckeditor-[version]/styles.js
 *
 */
CKEDITOR.stylesSet.add( 'mystyles', [ 
 { name: 'Inline Code', element: 'code' }, 
 { name: 'Inline Quotation', element: 'q' },
 { name: 'Left Aligned Photo', element: 'img', attributes: { 'class': 'align_left' } },
 { name: 'Right Aligned Photo', element: 'img', attributes: { 'class': 'align_right' } },
 { name: 'Centered Photo', element: 'img', attributes: { 'class': 'align_center' } }, 
 { name: 'Small', element: 'small' },
 { name: 'Deleted Text', element: 'del' },
 { name: 'Inserted Text', element: 'ins' },
 { name: 'Cited Work', element: 'cite' }
] );
