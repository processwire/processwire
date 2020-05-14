<?php namespace ProcessWire;

/*
:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::
::    GIFDecoder Version 2.0 by László Zsidi
::
::    Created at 2007. 02. 01. '07.47.AM'
::
::    Updated at 2009. 06. 23. '06.00.AM'
::
::  * Optimized version by xurei
::    https://github.com/xurei/GIFDecoder_optimized
::    Updated at 2015-04-13
::
:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

LICENSE: 
  All versions specify Apache 2.0 license (2018-01-31): 
  https://github.com/jacoka/GIFDecoder/blob/master/LICENSE
  https://github.com/xurei/GIFDecoder_optimized/blob/master/LICENSE

Namespace added by Horst for ProcessWire
*/

class ISEAG_GIFDecoder {
    var $GIF_TransparentR =  -1;
    var $GIF_TransparentG =  -1;
    var $GIF_TransparentB =  -1;
    var $GIF_TransparentI =   0;

    var $GIF_buffer = null;
    var $GIF_arrays = Array ( );
    var $GIF_delays = Array ( );
    var $GIF_dispos = Array ( );
    var $GIF_stream = "";
    var $GIF_string = "";
    var $GIF_bfseek =  0;
    var $GIF_anloop =  0;

    //xurei - frames metadata
    var $GIF_frames_meta =  Array();

    var $GIF_screen = Array ( );
    var $GIF_global = Array ( ); //global color map
    var $GIF_sorted;
    var $GIF_colorS; //
    var $GIF_colorC; //Size of global color table
    var $GIF_colorF; //if 1, global color table follows image descriptor
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFDecoder ( $GIF_pointer )
    ::
    */
    function __construct ( $GIF_pointer ) {
        $this->GIF_stream = $GIF_pointer;

        ISEAG_GIFDecoder::GIFGetByte ( 6 );
        ISEAG_GIFDecoder::GIFGetByte ( 7 );

        $this->GIF_screen = $this->GIF_buffer;
        $this->GIF_colorF = $this->GIF_buffer [ 4 ] & 0x80 ? 1 : 0;
        $this->GIF_sorted = $this->GIF_buffer [ 4 ] & 0x08 ? 1 : 0;
        $this->GIF_colorC = $this->GIF_buffer [ 4 ] & 0x07;
        $this->GIF_colorS = 2 << $this->GIF_colorC;

        if ( $this->GIF_colorF == 1 ) {
            ISEAG_GIFDecoder::GIFGetByte ( 3 * $this->GIF_colorS );
            $this->GIF_global = $this->GIF_buffer;
        }
        for ( $cycle = 1; $cycle; ) {
            if ( ISEAG_GIFDecoder::GIFGetByte ( 1 ) ) {
                switch ( $this->GIF_buffer [ 0 ] ) {
                    case 0x21:
                        ISEAG_GIFDecoder::GIFReadExtensions ( );
                        break;
                    case 0x2C:
                        ISEAG_GIFDecoder::GIFReadDescriptor ( );
                        break;
                    case 0x3B:
                        $cycle = 0;
                        break;
                }
            }
            else {
                $cycle = 0;
            }
        }
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFReadExtension ( )
    ::
    */
    function GIFReadExtensions ( ) {
        ISEAG_GIFDecoder::GIFGetByte ( 1 );
        if ( $this->GIF_buffer [ 0 ] == 0xff ) {
            for ( ; ; ) {
                ISEAG_GIFDecoder::GIFGetByte ( 1 );
                if ( ( $u = $this->GIF_buffer [ 0 ] ) == 0x00 ) {
                    break;
                }
                ISEAG_GIFDecoder::GIFGetByte ( $u );
                if ( $u == 0x03 ) {
                    $this->GIF_anloop = ( $this->GIF_buffer [ 1 ] | $this->GIF_buffer [ 2 ] << 8 );
                }
            }
        }
        else {
            for ( ; ; ) {
                ISEAG_GIFDecoder::GIFGetByte ( 1 );
                if ( ( $u = $this->GIF_buffer [ 0 ] ) == 0x00 ) {
                    break;
                }
                ISEAG_GIFDecoder::GIFGetByte ( $u );
                if ( $u == 0x04 ) {
                    $buf4 = count($this->GIF_buffer) >= 5 ? $this->GIF_buffer [ 4 ] : 0;
                    if ( $buf4 & 0x80 ) {
                        $this->GIF_dispos [ ] = ( $this->GIF_buffer [ 0 ] >> 2 ) - 1;
                    }
                    else {
                        $this->GIF_dispos [ ] = ( $this->GIF_buffer [ 0 ] >> 2 ) - 0;
                    }
                    $this->GIF_delays [ ] = ( $this->GIF_buffer [ 1 ] | $this->GIF_buffer [ 2 ] << 8 );
                    if ( $this->GIF_buffer [ 3 ] ) {
                        $this->GIF_TransparentI = $this->GIF_buffer [ 3 ];
                    }
                }
            }
        }
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFReadDescriptor ( )
    ::
    */
    function GIFReadDescriptor ( ) {
        $GIF_screen    = Array ( );

        ISEAG_GIFDecoder::GIFGetByte ( 9 );

        //xurei - metadata saving
        $this->GIF_frames_meta[] = array(
            'left'=>$this->GIF_buffer[0] + ($this->GIF_buffer[1] << 8),
            'top'=>$this->GIF_buffer[2] + ($this->GIF_buffer[3] << 8),
            'width'=>$this->GIF_buffer[4] + ($this->GIF_buffer[5] << 8),
            'height'=>$this->GIF_buffer[6] + ($this->GIF_buffer[7] << 8),
        );

        $GIF_screen = $this->GIF_buffer;
        $GIF_colorF = $this->GIF_buffer [ 8 ] & 0x80 ? 1 : 0;
        if ( $GIF_colorF ) {
            $GIF_code = $this->GIF_buffer [ 8 ] & 0x07;
            $GIF_sort = $this->GIF_buffer [ 8 ] & 0x20 ? 1 : 0;
        }
        else {
            $GIF_code = $this->GIF_colorC;
            $GIF_sort = $this->GIF_sorted;
        }
        $GIF_size = 2 << $GIF_code;
        $this->GIF_screen [ 4 ] &= 0x70;
        $this->GIF_screen [ 4 ] |= 0x80;
        $this->GIF_screen [ 4 ] |= $GIF_code;
        if ( $GIF_sort ) {
            $this->GIF_screen [ 4 ] |= 0x08;
        }

        /*
         *
         * GIF Data Begin
         *
         */
        if ( $this->GIF_TransparentI ) {
            $this->GIF_string = "GIF89a";
        }
        else {
            $this->GIF_string = "GIF87a";
        }
        ISEAG_GIFDecoder::GIFPutByte ( $this->GIF_screen );
        if ( $GIF_colorF == 1 ) {
            ISEAG_GIFDecoder::GIFGetByte ( 3 * $GIF_size );
            if ( $this->GIF_TransparentI ) {
                $this->GIF_TransparentR = $this->GIF_buffer [ 3 * $this->GIF_TransparentI + 0 ];
                $this->GIF_TransparentG = $this->GIF_buffer [ 3 * $this->GIF_TransparentI + 1 ];
                $this->GIF_TransparentB = $this->GIF_buffer [ 3 * $this->GIF_TransparentI + 2 ];
            }
            ISEAG_GIFDecoder::GIFPutByte ( $this->GIF_buffer );
        }
        else {
            if ( $this->GIF_TransparentI ) {
                $this->GIF_TransparentR = $this->GIF_global [ 3 * $this->GIF_TransparentI + 0 ];
                $this->GIF_TransparentG = $this->GIF_global [ 3 * $this->GIF_TransparentI + 1 ];
                $this->GIF_TransparentB = $this->GIF_global [ 3 * $this->GIF_TransparentI + 2 ];
            }
            ISEAG_GIFDecoder::GIFPutByte ( $this->GIF_global );
        }
        if ( $this->GIF_TransparentI ) {
            $this->GIF_string .= "!\xF9\x04\x1\x0\x0". chr ( $this->GIF_TransparentI ) . "\x0";
        }
        $this->GIF_string .= chr ( 0x2C );
        $GIF_screen [ 8 ] &= 0x40;
        ISEAG_GIFDecoder::GIFPutByte ( $GIF_screen );
        ISEAG_GIFDecoder::GIFGetByte ( 1 );
        ISEAG_GIFDecoder::GIFPutByte ( $this->GIF_buffer );
        for ( ; ; ) {
            ISEAG_GIFDecoder::GIFGetByte ( 1 );
            ISEAG_GIFDecoder::GIFPutByte ( $this->GIF_buffer );
            if ( ( $u = $this->GIF_buffer [ 0 ] ) == 0x00 ) {
                break;
            }

            /*for ($i=0; $i!=$u; ++$i)
            {
                $this->GIF_string .= $this->GIF_stream { $this->GIF_bfseek++ };
            }*/
            $this->GIF_string .= substr($this->GIF_stream, $this->GIF_bfseek, $u);
            $this->GIF_bfseek += $u;

            //GIFDecoder::GIFGetByte ( $u );
            //GIFDecoder::GIFPutByte ( $this->GIF_buffer );
        }
        $this->GIF_string .= chr ( 0x3B );
        /*
         *
         * GIF Data End
         *
         */
        $this->GIF_arrays [ ] = $this->GIF_string;
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFGetByte ( $len )
    ::
    */
    function GIFGetByte ( $len ) {
        $this->GIF_buffer = new \SplFixedArray($len);

        $l = strlen ( $this->GIF_stream );
        for ( $i = 0; $i < $len; $i++ ) {
            if ( $this->GIF_bfseek > $l ) {
                $this->GIF_buffer->setSize($i);
                return 0;
            }
            $this->GIF_buffer [$i] = ord ( $this->GIF_stream [ $this->GIF_bfseek++ ] );
        }
        return 1;
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFPutByte ( $bytes )
    ::
    */
    function GIFPutByte ( $bytes ) {
        $out = '';
        foreach ( $bytes as $byte ) {
            $out .= chr($byte);
        }
        $this->GIF_string .= $out;
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    PUBLIC FUNCTIONS
    ::
    ::
    ::    GIFGetFrames ( )
    ::
    */
    function GIFGetFrames ( ) {
        return ( $this->GIF_arrays );
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFGetFramesMeta ( )
    ::
    ::  xurei - returns metadata as an array of arrays
    */
    function GIFGetFramesMeta ( ) {
        return ( $this->GIF_frames_meta );
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFGetDelays ( )
    ::
    */
    function GIFGetDelays ( ) {
        return ( $this->GIF_delays );
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFGetLoop ( )
    ::
    */
    function GIFGetLoop ( ) {
        return ( $this->GIF_anloop );
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFGetDisposal ( )
    ::
    */
    function GIFGetDisposal ( ) {
        return ( $this->GIF_dispos );
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFGetTransparentR ( )
    ::
    */
    function GIFGetTransparentR ( ) {
        return ( $this->GIF_TransparentR );
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFGetTransparentG ( )
    ::
    */
    function GIFGetTransparentG ( ) {
        return ( $this->GIF_TransparentG );
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFGetTransparentB ( )
    ::
    */
    function GIFGetTransparentB ( ) {
        return ( $this->GIF_TransparentB );
    }
    /*
    :::::::::::::::::::::::::::::::::::::::::::::::::::
    ::
    ::    GIFGetTransparentI ( )
    ::
    */
    function GIFGetTransparentI ( ) {
        return ( $this->GIF_TransparentI );
    }
}


