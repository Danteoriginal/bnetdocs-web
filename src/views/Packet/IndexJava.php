<?php

namespace BNETDocs\Views\Packet;

use \CarlBennett\MVC\Libraries\Common;
use \BNETDocs\Libraries\Exceptions\IncorrectModelException;
use \BNETDocs\Libraries\Model;
use \BNETDocs\Libraries\View;
use \BNETDocs\Models\Packet\Index as PacketIndexModel;

class IndexJava extends View {

  public function getMimeType() {
    return "text/x-java-source;charset=utf-8";
  }

  public function render(Model &$model) {

    if (!$model instanceof PacketIndexModel) {
      throw new IncorrectModelException();
    }

    echo "/**\n";
    echo " *  BNETDocs, the Battle.net(TM) protocol documentation and discussion website\n";
    echo " *  Copyright (C) 2008-2016  Carl Bennett\n";
    echo " *  <" . Common::relativeUrlToAbsolute("/legal") . ">\n";
    echo " *\n";
    echo " *  BNETDocs is free software: you can redistribute it and/or modify\n";
    echo " *  it under the terms of the GNU Affero General Public License as published by\n";
    echo " *  the Free Software Foundation, either version 3 of the License, or\n";
    echo " *  (at your option) any later version.\n";
    echo " *\n";
    echo " *  BNETDocs is distributed in the hope that it will be useful,\n";
    echo " *  but WITHOUT ANY WARRANTY; without even the implied warranty of\n";
    echo " *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the\n";
    echo " *  GNU Affero General Public License for more details.\n";
    echo " *\n";
    echo " *  You should have received a copy of the GNU Affero General Public License\n";
    echo " *  along with BNETDocs.  If not, see <http://www.gnu.org/licenses/>.\n";
    echo " *\n";

    echo " *  Packet ID constants for Java\n";
    echo " *  Generated by BNETDocs on " . $model->timestamp->format("r") . "\n";
    echo " */\n";

    echo "\n";

    foreach ($model->packets as $pkt) {
      echo "static final byte " . $pkt->getPacketName() . " = "
        . $pkt->getPacketId(true) . ";\n";
    }

  }

}
