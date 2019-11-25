<!DOCTYPE html>
<?php

require 'inc/config.php'; 
require 'inc/functions.php';

if (!empty($_POST)) {
    foreach ($_POST as $key=>$value) {            
        $var_concluido["doc"]["concluido"] = $value;
        $var_concluido["doc"]["doc_as_upsert"] = true; 
        Elasticsearch::update($key, $var_concluido);
    }
    sleep(6);
    header("Refresh:0");
}

if (isset($_GET["filter"])) {
    if (!in_array("type:\"Work\"", $_GET["filter"])) {
        $_GET["filter"][] = "type:\"Work\"";
    }
} else {
    $_GET["filter"][] = "type:\"Work\"";
}



if (isset($fields)) {
    $_GET["fields"] = $fields;
}
$result_get = Requests::getParser($_GET);
$limit = $result_get['limit'];
$page = $result_get['page'];
$params = [];
$params["index"] = $index;
$params["body"] = $result_get['query'];
$cursorTotal = $client->count($params);
$total = $cursorTotal["count"];
if (isset($_GET["sort"])) {
    $result_get['query']["sort"][$_GET["sort"]]["unmapped_type"] = "long";
    $result_get['query']["sort"][$_GET["sort"]]["missing"] = "_last";
    $result_get['query']["sort"][$_GET["sort"]]["order"] = "desc";
    $result_get['query']["sort"][$_GET["sort"]]["mode"] = "max";
} else {
    $result_get['query']['sort']['datePublished.keyword']['order'] = "desc";
    $result_get['query']["sort"]["_uid"]["unmapped_type"] = "long";
    $result_get['query']["sort"]["_uid"]["missing"] = "_last";
    $result_get['query']["sort"]["_uid"]["order"] = "desc";
    $result_get['query']["sort"]["_uid"]["mode"] = "max";
}
$params["body"] = $result_get['query'];
$params["size"] = $limit;
$params["from"] = $result_get['skip'];
$cursor = $client->search($params);


// $result_get = Requests::getParser($_GET);
// $query = $result_get['query'];
// $limit = $result_get['limit'];
// $page = $result_get['page'];
// $skip = $result_get['skip'];

// //$query['sort'] = [
// //    ['datePublished' => ['order' => 'desc']],
// //];

// $params = [];
// $params["index"] = $index;
// $params["size"] = $limit;
// $params["from"] = $skip;
// $params["body"] = $query;

// $cursor = $client->search($params);
// $total = $cursor["hits"]["total"];

/*pagination - start*/
$get_data = $_GET;    
/*pagination - end*/      

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <?php
            include('inc/meta-header-new.php'); 
        ?>        
        <title>Lattes USP - Resultado da busca por trabalhos</title>
        
        <script src="http://cdn.jsdelivr.net/g/filesaver.js"></script>
        <script>
              function SaveAsFile(t,f,m) {
                    try {
                        var b = new Blob([t],{type:m});
                        saveAs(b, f);
                    } catch (e) {
                        window.open("data:"+m+"," + encodeURIComponent(t), '_blank','');
                    }
                }
        </script>         
        
    </head>
    <body>

        <!-- NAV -->
        <?php require 'inc/navbar.php'; ?>
        <!-- /NAV -->
        <br/><br/><br/><br/>

        <main role="main">
            <div class="container">

            <div class="row">
                <div class="col-8">    

                    <!-- Navegador de resultados - Início -->
                    <?php ui::pagination($page, $total, $limit); ?>
                    <!-- Navegador de resultados - Fim -->   

                    <?php foreach ($cursor["hits"]["hits"] as $r) : ?>
                        <?php if (empty($r["_source"]['datePublished'])) {
                            $r["_source"]['datePublished'] = "";
                        }
                        ?>

                        <div class="card">
                            <div class="card-body">

                                <h6 class="card-subtitle mb-2 text-muted"><?php echo $r["_source"]['tipo'];?>
                                <h5 class="card-title"><a class="text-dark" href="<?php echo $r['_source']['url']; ?>"><?php echo $r["_source"]['name']; ?> (<?php echo $r["_source"]['datePublished'];?>)</a></h5>

                                <p class="text-muted"><b>Autores:</b>
                                    <?php if (!empty($r["_source"]['author'])) : ?>
                                        <?php foreach ($r["_source"]['author'] as $autores) {
                                            $authors_array[]='<a href="result.php?filter[]=author.person.name:&quot;'.$autores["person"]["name"].'&quot;">'.$autores["person"]["name"].'</a>';
                                        } 
                                        $array_aut = implode(", ",$authors_array);
                                        unset($authors_array);
                                        print_r($array_aut);
                                        ?>
                                    <?php endif; ?>
                                </p>
                                
   
                                <?php if (!empty($r["_source"]['isPartOf']['name'])) : ?>                                        
                                    <p class="text-muted"><b>In:</b> <a href="result.php?filter[]=periodico.titulo_do_periodico:&quot;<?php echo $r["_source"]['isPartOf']['name'];?>&quot;"><?php echo $r["_source"]['isPartOf']['name'];?></a></p>
                                <?php endif; ?>
                                <?php if (!empty($r["_source"]['isPartOf']['issn'])) : ?>
                                    <p class="text-muted"><b>ISSN:</b> <a href="result.php?filter[]=periodico.issn:&quot;<?php echo $r["_source"]['isPartOf']['issn'];?>&quot;"><?php echo $r["_source"]['isPartOf']['issn'];?></a></li>                                        
                                <?php endif; ?>
                                
                                <?php if (!empty($r["_source"]['doi'])) : ?>
                                    <p class="text-muted"><b>DOI:</b>    <a href="https://doi.org/<?php echo $r["_source"]['doi'];?>"><?php echo $r["_source"]['doi'];?></a></p>
                                    <p><a href="doi_to_elastic.php?doi=<?php echo $r['_source']['doi'];?>&tag=<?php echo $r['_source']['tag'][0];?>">Coletar dados da Crossref</a></p>                                        
                                <?php endif; ?>                                        
                                
                                <?php if (!empty($r["_source"]['ids_match'])) : ?>  
                                    <?php foreach ($r["_source"]['ids_match'] as $id_match) : ?>
                                        <?php compararRegistros::match_id($id_match["id_match"], $id_match["nota"]);?>
                                    <?php endforeach;?>
                                <?php endif; ?>
                                        
                                <?php 
                                if ($instituicao == "USP") {
                                    DadosExternos::query_bdpi($r["_source"]['name'], $r["_source"]['datePublished'], $r['_id']);
                                }
                                ?>  

           

                                    <div class="btn-group mt-3" role="group" aria-label="Botoes">

                                        <form method="post">
                                            <?php if(isset($r["_source"]["concluido"])) : ?>
                                                <?php if($r["_source"]["concluido"]== "Sim") : ?>                                                  
                                                    
                                                        <label><input type='hidden' value='Não' name="<?php echo $r['_id'];?>"></label>      
                                                        <button class="btn btn-primary">Desmarcar como concluído</button>
                                                
                                                <?php else : ?>
                                                    
                                                        <label><input type='hidden' value='Sim' name="<?php echo $r['_id'];?>"></label>
                                                        <button class="btn btn-primary">Marcar como concluído</button>
                                                    
                                                <?php endif; ?>                                    
                                            <?php else : ?>
                                                    
                                                        <label><input type='hidden' value='Sim' name="<?php echo $r['_id'];?>"></label>
                                                        <button class="btn btn-primary">Marcar como concluído</button>
                                                    
                                            <?php endif; ?>
                                            
                                        </form>                                       
                                        
                                        <?php                                        
                                        if (isset($dspaceRest)) { 
                                            echo '<form action="dspaceConnect.php" method="get">
                                                <input type="hidden" name="createRecord" value="true" />
                                                <input type="hidden" name="_id" value="'.$r['_id'].'" />
                                                <button class="uk-button uk-button-danger" name="btn_submit">Criar registro no DSpace</button>
                                                </form>';  
                                        }                                        
                                        ?>
                                        
                                        <a href="tools/export.php?search[]=_id:<?php echo $r['_id'] ?>&format=alephseq" class="btn btn-secondary">Exportar Alephseq</a>
                                        <a href="editor.php?_id=<?php echo $r['_id'] ?>" class="btn btn-warning">Editar registro</a>

                                    </div>

                            </div>
                        </div>
                        <?php endforeach;?>


                        <!-- Navegador de resultados - Início -->
                        <?php ui::pagination($page, $total, $limit); ?>
                        <!-- Navegador de resultados - Fim -->  

                </div>
                <div class="col-4">















                
                <hr>
                <h3>Refinar meus resultados</h3>    
                <hr>
                <?php
                    $facets = new facets();
                    $facets->query = $result_get['query'];

                    if (!isset($_GET)) {
                        $_GET = null;                                    
                    }                       
                    
                    $facets->facet("Lattes.natureza", 100, "Natureza", null, "_term", $_GET);
                    $facets->facet("tipo", 100, "Tipo de material", null, "_term", $_GET);
                    $facets->facet("tag", 100, "Tag", null, "_term", $_GET);
                    
                    $facets->facet("author.person.name", 100, "Nome completo do autor", null, "_term", $_GET);
                    $facets->facet("lattes_ids", 100, "Número do lattes", null, "_term", $_GET);
                    $facets->facet("USP.codpes",100,"Número USP",null,"_term",$_GET);
                    $facets->facet("USP.unidadeUSP",100,"Unidade USP",null,"_term",$_GET);
                    
                    $facets->facet("country",200,"País de publicação",null,"_term",$_GET);
                    $facets->facet("datePublished",120,"Ano de publicação","desc","_term",$_GET);
                    $facets->facet("language",40,"Idioma",null,"_term",$_GET);
                    $facets->facet("Lattes.meioDeDivulgacao",100,"Meio de divulgação",null,"_term",$_GET);
                    $facets->facet("about",100,"Palavras-chave",null,"_term",$_GET);
                    $facets->facet("agencia_de_fomento",100,"Agências de fomento",null,"_term",$_GET);

                    $facets->facet("Lattes.flagRelevancia",100,"Relevância",null,"_term",$_GET);
                    $facets->facet("Lattes.flagDivulgacaoCientifica",100,"Divulgação científica",null,"_term",$_GET);
                    
                    $facets->facet("area_do_conhecimento.nomeGrandeAreaDoConhecimento", 100, "Nome da Grande Área do Conhecimento", null, "_term", $_GET);
                    $facets->facet("area_do_conhecimento.nomeDaAreaDoConhecimento", 100, "Nome da Área do Conhecimento", null, "_term", $_GET);
                    $facets->facet("area_do_conhecimento.nomeDaSubAreaDoConhecimento", 100, "Nome da Sub Área do Conhecimento", null, "_term", $_GET);
                    $facets->facet("area_do_conhecimento.nomeDaEspecialidade", 100, "Nome da Especialidade", null, "_term", $_GET);
                    
                    $facets->facet("trabalhoEmEventos.classificacaoDoEvento", 100, "Classificação do evento", null, "_term", $_GET); 
                    $facets->facet("EducationEvent.name", 100, "Nome do evento", null, "_term", $_GET);
                    $facets->facet("publisher.organization.location", 100, "Cidade do evento", null, "_term", $_GET);
                    $facets->facet("trabalhoEmEventos.anoDeRealizacao", 100, "Ano de realização do evento", null, "_term", $_GET);
                    $facets->facet("trabalhoEmEventos.tituloDosAnaisOuProceedings", 100, "Título dos anais", null, "_term", $_GET);
                    $facets->facet("trabalhoEmEventos.isbn", 100, "ISBN dos anais", null, "_term", $_GET);
                    $facets->facet("trabalhoEmEventos.nomeDaEditora", 100, "Editora dos anais", null, "_term", $_GET);
                    $facets->facet("trabalhoEmEventos.cidadeDaEditora", 100, "Cidade da editora", null, "_term", $_GET);

                    $facets->facet("midiaSocialWebsiteBlog.formacao_maxima", 100, "Formação máxima - Blogs e mídias sociais", null, "_term", $_GET);
                    
                    $facets->facet("isPartOf.name", 100, "Título do periódico", null, "_term", $_GET);

                    $facets->facet("concluido", 100, "Concluído", null, "_term", $_GET);
                    $facets->facet("bdpi.existe", 100, "Está no DEDALUS?", null, "_term", $_GET);

                ?>
                </ul>
                <!-- Limitar por data - Início -->
                <form action="result.php?" method="GET">
                    <h5 class="mt-3">Filtrar por ano de publicação</h5>
                    <?php 
                        parse_str($_SERVER["QUERY_STRING"], $parsedQuery);
                        foreach ($parsedQuery as $k => $v) {
                            if (is_array($v)) {
                                foreach ($v as $v_unit) {
                                    echo '<input type="hidden" name="'.$k.'[]" value="'.htmlentities($v_unit).'">';
                                }
                            } else {
                                if ($k == "initialYear") {
                                    $initialYearValue = $v;
                                } elseif ($k == "finalYear") {
                                    $finalYearValue = $v;
                                } else {
                                    echo '<input type="hidden" name="'.$k.'" value="'.htmlentities($v).'">';
                                }                                    
                            }
                        }

                        if (!isset($initialYearValue)) {
                            $initialYearValue = "";
                        }                            
                        if (!isset($finalYearValue)) {
                            $finalYearValue = "";
                        }

                    ?>
                    <div class="form-group">
                        <label for="initialYear">Ano inicial</label>
                        <input type="text" class="form-control" id="initialYear" name="initialYear" pattern="\d{4}" placeholder="Ex. 2010" value="<?php echo $initialYearValue; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="finalYear">Ano final</label>
                        <input type="text" class="form-control" id="finalYear" name="finalYear" pattern="\d{4}" placeholder="Ex. 2020" value="<?php echo $finalYearValue; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </form>   
                <!-- Limitar por data - Fim -->
                <hr>     
                        
            </div>
        </div>
                

        <?php include('inc/footer.php'); ?>

        </div>
        
    </body>
</html>