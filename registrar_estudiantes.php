<td>

<a href="editar_estudiante.php?id=<?php echo $e['id']; ?>" class="btn btn-warning btn-sm">
Editar
</a>

<a href="?eliminar=<?php echo $e['id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('¿Eliminar estudiante?')">
Eliminar
</a>

</td>