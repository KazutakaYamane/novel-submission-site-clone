import { revalidateTag } from "next/cache";
import { handleRevalidate } from "./handle-revalidate";

export async function POST(request: Request) {
  return handleRevalidate(request, { secret: process.env.REVALIDATE_SECRET, revalidateTag });
}
